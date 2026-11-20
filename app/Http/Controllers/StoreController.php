<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StoreProduct;
use App\Notifications\OrderConfirmedNotification;
use App\Services\CjDropshippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StoreController extends Controller
{
    public function __construct(
        private readonly CjDropshippingService $cjService
    ) {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function index(Request $request): View
    {
        $query = StoreProduct::query()->where('is_active', true);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($category = trim((string) $request->query('category', ''))) {
            $query->where('category_name', $category);
        }

        match ($request->query('sort')) {
            'price_asc'  => $query->orderBy('retail_price', 'asc'),
            'price_desc' => $query->orderBy('retail_price', 'desc'),
            'newest'     => $query->orderByDesc('created_at'),
            default      => $query->orderByDesc('is_featured')->orderByDesc('id'),
        };

        $products = $query->paginate(12)->withQueryString();

        $categories = StoreProduct::query()
            ->where('is_active', true)
            ->whereNotNull('category_name')
            ->where('category_name', '!=', '')
            ->distinct()
            ->orderBy('category_name')
            ->pluck('category_name');

        return view('store.index', [
            'products'   => $products,
            'categories' => $categories,
            'search'     => $search,
            'category'   => $category,
            'sort'       => (string) $request->query('sort', 'featured'),
        ]);
    }

    public function show(StoreProduct $storeProduct): View
    {
        abort_unless($storeProduct->is_active, 404);

        $supportPhone = Setting::get('mobile_money_phone')
            ?? Setting::get('support_phone')
            ?? config('app.support_phone', '+1 (800) 555-0199');

        $deliveryOptions = Schema::hasTable('delivery_options')
            ? DeliveryOption::where('is_active', true)->get()
            : collect();

        $relatedProducts = StoreProduct::query()
            ->where('is_active', true)
            ->where('id', '!=', $storeProduct->id)
            ->when($storeProduct->category_name, fn ($q) => $q->where('category_name', $storeProduct->category_name))
            ->limit(4)
            ->get();

        return view('store.show', compact('storeProduct', 'supportPhone', 'deliveryOptions', 'relatedProducts'));
    }

    public function cart(): View
    {
        $hydrated = $this->hydrateCart();
        $subtotal = round(collect($hydrated)->sum('subtotal'), 2);
        $taxAmount = ! empty($hydrated) ? $this->cjService->calculateCartTaxAmount($hydrated) : 0.0;
        $shippingOptions = ! empty($hydrated)
            ? $this->cjService->calculateCartShippingOptions($hydrated, 'United States')
            : [];

        $supportPhone = Setting::get('mobile_money_phone')
            ?? Setting::get('support_phone')
            ?? config('app.support_phone', '+1 (800) 555-0199');

        return view('store.cart', [
            'cartItems'       => $this->serializeCartItems($hydrated),
            'subtotal'        => $subtotal,
            'taxAmount'       => $taxAmount,
            'shippingOptions' => $shippingOptions,
            'supportPhone'    => $supportPhone,
        ]);
    }

    public function addToCart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_product_id' => 'required|exists:store_products,id',
            'vid'              => 'nullable|string|max:191',
            'quantity'         => 'nullable|integer|min:1|max:100',
        ]);

        $product = StoreProduct::where('is_active', true)->findOrFail($data['store_product_id']);
        $variant = $this->resolveVariant($product, $data['vid'] ?? null);
        $vid = $variant['vid'] ?? $product->cj_vid;
        $quantity = (int) ($data['quantity'] ?? 1);

        $key = $this->buildCartKey($product->id, $vid);
        $cart = session('store_cart', []);

        $existingQty = (int) ($cart[$key]['quantity'] ?? 0);
        $newQty = min(100, $existingQty + $quantity);

        $cart[$key] = [
            'store_product_id' => $product->id,
            'vid'              => $vid,
            'quantity'         => $newQty,
        ];

        session(['store_cart' => $cart]);

        return $this->buildCartSummaryResponse([
            'message' => "{$product->name} added to your cart.",
            'added'   => $key,
        ]);
    }

    public function updateCart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key'      => 'required|string|max:255',
            'quantity' => 'required|integer|min:0|max:100',
        ]);

        $cart = session('store_cart', []);
        if (! isset($cart[$data['key']])) {
            return response()->json(['success' => false, 'error' => 'Cart item not found.'], 404);
        }

        if ((int) $data['quantity'] <= 0) {
            unset($cart[$data['key']]);
        } else {
            $cart[$data['key']]['quantity'] = (int) $data['quantity'];
        }

        session(['store_cart' => $cart]);

        return $this->buildCartSummaryResponse(['message' => 'Cart updated.']);
    }

    public function removeFromCart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string|max:255',
        ]);

        $cart = session('store_cart', []);
        unset($cart[$data['key']]);
        session(['store_cart' => $cart]);

        return $this->buildCartSummaryResponse(['message' => 'Item removed from cart.']);
    }

    public function clearCart(): JsonResponse
    {
        session()->forget('store_cart');

        return response()->json([
            'success'    => true,
            'cart_count' => 0,
            'items'      => [],
            'subtotal'   => 0.0,
            'tax_amount' => 0.0,
        ]);
    }

    public function calculateCartShipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => 'required|string|max:100',
        ]);

        $hydrated = $this->hydrateCart();
        if (empty($hydrated)) {
            return response()->json([
                'success' => false,
                'error'   => 'Your shopping cart is empty.',
            ], 422);
        }

        $subtotal = round(collect($hydrated)->sum('subtotal'), 2);
        $taxAmount = $this->cjService->calculateCartTaxAmount($hydrated);
        $options = $this->cjService->calculateCartShippingOptions($hydrated, $data['country']);

        return response()->json([
            'items'            => $this->serializeCartItems($hydrated),
            'subtotal'         => $subtotal,
            'tax_amount'       => $taxAmount,
            'shipping_options' => $options,
        ]);
    }

    public function processCartCardCheckout(Request $request): JsonResponse
    {
        $data = $this->validateCartCheckoutRequest($request, true);
        $hydrated = $this->hydrateCart();

        if (empty($hydrated)) {
            return response()->json([
                'success' => false,
                'error'   => 'Your shopping cart is empty.',
            ], 422);
        }

        $options = $this->cjService->calculateCartShippingOptions($hydrated, (string) $data['shipping_address']['country']);
        $shippingCost = $this->matchShippingOptionCost($options, (string) $data['logistic_name'], (float) $data['shipping_cost']);
        $subtotal = round(collect($hydrated)->sum('subtotal'), 2);
        $taxAmount = $this->cjService->calculateCartTaxAmount($hydrated);
        $totalCharged = round($subtotal + $shippingCost + $taxAmount, 2);

        $stripeSecret = config('cashier.secret');
        $isMock = empty($stripeSecret)
            || str_contains((string) $stripeSecret, 'your_secret_key_here')
            || str_starts_with($data['payment_method_id'], 'pm_mock_');

        if ($isMock) {
            return $this->finalizeCartOrder(
                $hydrated,
                $data,
                $shippingCost,
                $taxAmount,
                $totalCharged,
                'pi_mock_' . Str::random(16),
                'pending',
                'succeeded'
            );
        }

        $totalCents = (int) round($totalCharged * 100);

        try {
            $intent = PaymentIntent::create([
                'amount'                    => $totalCents,
                'currency'                  => 'usd',
                'payment_method'            => $data['payment_method_id'],
                'confirm'                   => true,
                'automatic_payment_methods' => [
                    'enabled'         => true,
                    'allow_redirects' => 'never',
                ],
                'description'               => 'Jubilee Direct Store Cart (' . count($hydrated) . ' items)',
                'receipt_email'             => $data['customer_email'],
                'metadata'                  => [
                    'cart_item_count' => count($hydrated),
                    'customer_name'   => $data['customer_name'],
                    'customer_email'  => $data['customer_email'],
                ],
            ]);

            if ($intent->status === 'succeeded') {
                return $this->finalizeCartOrder(
                    $hydrated,
                    $data,
                    $shippingCost,
                    $taxAmount,
                    $totalCharged,
                    $intent->id,
                    'pending',
                    'succeeded'
                );
            }

            return response()->json([
                'success' => false,
                'error'   => 'Payment requires extra authentication or verification.',
            ], 402);
        } catch (\Stripe\Exception\CardException $e) {
            return response()->json(['success' => false, 'error' => $e->getError()->message], 402);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Payment processing failed: ' . $e->getMessage()], 500);
        }
    }

    public function processCartMobileMoneyCheckout(Request $request): JsonResponse
    {
        $data = $this->validateCartCheckoutRequest($request, false);
        $hydrated = $this->hydrateCart();

        if (empty($hydrated)) {
            return response()->json([
                'success' => false,
                'error'   => 'Your shopping cart is empty.',
            ], 422);
        }

        $options = $this->cjService->calculateCartShippingOptions($hydrated, (string) $data['shipping_address']['country']);
        $shippingCost = $this->matchShippingOptionCost($options, (string) $data['logistic_name'], (float) $data['shipping_cost']);
        $subtotal = round(collect($hydrated)->sum('subtotal'), 2);
        $taxAmount = $this->cjService->calculateCartTaxAmount($hydrated);
        $totalCharged = round($subtotal + $shippingCost + $taxAmount, 2);

        return $this->finalizeCartOrder(
            $hydrated,
            $data,
            $shippingCost,
            $taxAmount,
            $totalCharged,
            'momo_' . Str::random(16),
            'pending_payment',
            'pending',
            ['payment_method' => 'mobile_money', 'cart_checkout' => true]
        );
    }

    public function calculateShipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_product_id' => 'required|exists:store_products,id',
            'vid'              => 'nullable|string|max:191',
            'quantity'         => 'required|integer|min:1|max:100',
            'country'          => 'required|string|max:100',
        ]);

        $product = StoreProduct::where('is_active', true)->findOrFail($data['store_product_id']);
        $variant = $this->resolveVariant($product, $data['vid'] ?? null);
        $vid = $variant['vid'] ?? $product->cj_vid;
        $unitPrice = (float) ($variant['retail_price'] ?? $product->retail_price);
        $quantity = (int) $data['quantity'];
        $subtotal = round($unitPrice * $quantity, 2);
        $taxAmount = $product->calculateTaxAmount($subtotal, $quantity);

        $options = $this->cjService->calculateStoreShippingOptions(
            $product,
            $data['country'],
            $vid,
            $quantity
        );

        return response()->json([
            'unit_price'       => round($unitPrice, 2),
            'subtotal'         => $subtotal,
            'tax_amount'       => $taxAmount,
            'shipping_options' => $options,
        ]);
    }

    public function processCardCheckout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_product_id'             => 'required|exists:store_products,id',
            'vid'                          => 'nullable|string|max:191',
            'quantity'                     => 'required|integer|min:1|max:100',
            'logistic_name'                => 'required|string|max:191',
            'shipping_cost'                => 'required|numeric|min:0|max:99999',
            'payment_method_id'            => 'required|string|max:255',
            'customer_name'                => 'required|string|max:200',
            'customer_email'               => 'required|email|max:200',
            'customer_phone'               => 'required|string|max:50',
            'shipping_address'             => 'required|array',
            'shipping_address.line1'       => 'required|string|max:255',
            'shipping_address.line2'       => 'nullable|string|max:255',
            'shipping_address.city'        => 'required|string|max:100',
            'shipping_address.state'       => 'nullable|string|max:100',
            'shipping_address.postal_code' => 'required|string|max:20',
            'shipping_address.country'     => 'required|string|max:100',
            'customer_notes'               => 'nullable|string|max:1000',
        ]);

        $product = StoreProduct::where('is_active', true)->findOrFail($data['store_product_id']);
        [$unitPrice, $vid, $weightGrams] = $this->resolvePricingDetails($product, $data['vid'] ?? null);
        $quantity = (int) $data['quantity'];
        $shippingCost = $this->verifyShippingCost(
            $product,
            $vid,
            $quantity,
            (string) $data['shipping_address']['country'],
            (string) $data['logistic_name'],
            (float) $data['shipping_cost']
        );

        $subtotal = round($unitPrice * $quantity, 2);
        $taxAmount = $product->calculateTaxAmount($subtotal, $quantity);
        $totalCharged = round($subtotal + $shippingCost + $taxAmount, 2);

        $stripeSecret = config('cashier.secret');
        $isMock = empty($stripeSecret)
            || str_contains((string) $stripeSecret, 'your_secret_key_here')
            || str_starts_with($data['payment_method_id'], 'pm_mock_');

        if ($isMock) {
            $intentId = 'pi_mock_' . Str::random(16);

            return $this->finalizePaidOrder(
                $product,
                $data,
                $unitPrice,
                $vid,
                $weightGrams,
                $shippingCost,
                $taxAmount,
                $totalCharged,
                $intentId
            );
        }

        $totalCents = (int) round($totalCharged * 100);

        try {
            $intent = PaymentIntent::create([
                'amount'                    => $totalCents,
                'currency'                  => 'usd',
                'payment_method'            => $data['payment_method_id'],
                'confirm'                   => true,
                'automatic_payment_methods' => [
                    'enabled'         => true,
                    'allow_redirects' => 'never',
                ],
                'description'               => "Jubilee Direct Store: {$product->name}",
                'receipt_email'             => $data['customer_email'],
                'metadata'                  => [
                    'store_product_id' => $product->id,
                    'cj_pid'           => $product->cj_pid,
                    'customer_name'    => $data['customer_name'],
                    'customer_email'   => $data['customer_email'],
                ],
            ]);

            if ($intent->status === 'succeeded') {
                return $this->finalizePaidOrder(
                    $product,
                    $data,
                    $unitPrice,
                    $vid,
                    $weightGrams,
                    $shippingCost,
                    $taxAmount,
                    $totalCharged,
                    $intent->id
                );
            }

            return response()->json([
                'success' => false,
                'error'   => 'Payment requires extra authentication or verification.',
            ], 402);
        } catch (\Stripe\Exception\CardException $e) {
            return response()->json(['success' => false, 'error' => $e->getError()->message], 402);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Payment processing failed: ' . $e->getMessage()], 500);
        }
    }

    public function processMobileMoneyCheckout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_product_id'             => 'required|exists:store_products,id',
            'vid'                          => 'nullable|string|max:191',
            'quantity'                     => 'required|integer|min:1|max:100',
            'logistic_name'                => 'required|string|max:191',
            'shipping_cost'                => 'required|numeric|min:0|max:99999',
            'customer_name'                => 'required|string|max:200',
            'customer_email'               => 'required|email|max:200',
            'customer_phone'               => 'required|string|max:50',
            'shipping_address'             => 'required|array',
            'shipping_address.line1'       => 'required|string|max:255',
            'shipping_address.line2'       => 'nullable|string|max:255',
            'shipping_address.city'        => 'required|string|max:100',
            'shipping_address.state'       => 'nullable|string|max:100',
            'shipping_address.postal_code' => 'required|string|max:20',
            'shipping_address.country'     => 'required|string|max:100',
            'customer_notes'               => 'nullable|string|max:1000',
        ]);

        $product = StoreProduct::where('is_active', true)->findOrFail($data['store_product_id']);
        [$unitPrice, $vid, $weightGrams] = $this->resolvePricingDetails($product, $data['vid'] ?? null);
        $quantity = (int) $data['quantity'];
        $shippingCost = $this->verifyShippingCost(
            $product,
            $vid,
            $quantity,
            (string) $data['shipping_address']['country'],
            (string) $data['logistic_name'],
            (float) $data['shipping_cost']
        );

        $subtotal = round($unitPrice * $quantity, 2);
        $taxAmount = $product->calculateTaxAmount($subtotal, $quantity);
        $totalCharged = round($subtotal + $shippingCost + $taxAmount, 2);
        $momoRef = 'momo_' . Str::random(16);

        $order = $this->createStoreOrder(
            $product,
            $data,
            $unitPrice,
            $vid,
            $weightGrams,
            $shippingCost,
            $taxAmount,
            $totalCharged,
            $momoRef,
            'pending_payment'
        );

        Payment::create([
            'order_id'                 => $order->id,
            'stripe_payment_intent_id' => $momoRef,
            'type'                     => 'initial',
            'amount'                   => $totalCharged,
            'currency'                 => 'usd',
            'status'                   => 'pending',
            'stripe_metadata'          => [
                'payment_method' => 'mobile_money',
                'store_product'  => $product->name,
            ],
        ]);

        if ($this->cjService->shouldAutoFulfill() && filled($order->cj_vid)) {
            $this->cjService->fulfillOrder($order);
        }

        try {
            $order->notify(new OrderConfirmedNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Notification failed during Store Mobile Money order: ' . $e->getMessage());
        }

        return response()->json([
            'success'  => true,
            'redirect' => route('order.success') . '?session_id=' . $momoRef,
        ]);
    }

    private function finalizePaidOrder(
        StoreProduct $product,
        array $data,
        float $unitPrice,
        ?string $vid,
        int $weightGrams,
        float $shippingCost,
        float $taxAmount,
        float $totalCharged,
        string $paymentIntentId
    ): JsonResponse {
        $order = $this->createStoreOrder(
            $product,
            $data,
            $unitPrice,
            $vid,
            $weightGrams,
            $shippingCost,
            $taxAmount,
            $totalCharged,
            $paymentIntentId,
            'pending'
        );

        Payment::create([
            'order_id'                 => $order->id,
            'stripe_payment_intent_id' => $paymentIntentId,
            'type'                     => 'initial',
            'amount'                   => $totalCharged,
            'currency'                 => 'usd',
            'status'                   => 'succeeded',
        ]);

        if ($this->cjService->shouldAutoFulfill() && filled($order->cj_vid)) {
            $this->cjService->fulfillOrder($order);
        }

        try {
            $order->notify(new OrderConfirmedNotification($order));
        } catch (\Throwable $e) {
            Log::warning('Notification failed during Store order: ' . $e->getMessage());
        }

        return response()->json([
            'success'  => true,
            'redirect' => route('order.success') . '?session_id=' . $paymentIntentId,
        ]);
    }

    private function createStoreOrder(
        StoreProduct $product,
        array $data,
        float $unitPrice,
        ?string $vid,
        int $weightGrams,
        float $shippingCost,
        float $taxAmount,
        float $totalCharged,
        string $paymentIntentId,
        string $cjInitialStatus
    ): Order {
        $order = Order::create([
            'store_product_id'         => $product->id,
            'cj_pid'                   => $product->cj_pid,
            'cj_vid'                   => $vid,
            'cj_order_status'          => $cjInitialStatus,
            'cj_logistic_name'         => $data['logistic_name'],
            'cj_shipping_cost'         => $shippingCost,
            'tax_amount'               => $taxAmount,
            'order_number'             => Order::generateOrderNumber(),
            'product_url'              => route('store.show', $product),
            'product_name'             => $product->name,
            'product_image_url'        => $product->display_image_url,
            'source_platform'          => 'other',
            'quantity'                 => (int) $data['quantity'],
            'size_tier'                => 'small',
            'shipping_method'          => $data['logistic_name'],
            'product_weight'           => round(($weightGrams * (int) $data['quantity']) / 1000, 2),
            'estimated_product_price'  => $unitPrice,
            'service_fee'              => $taxAmount,
            'size_handling_fee'        => $shippingCost,
            'total_charged'            => $totalCharged,
            'customer_name'            => $data['customer_name'],
            'customer_email'           => $data['customer_email'],
            'customer_phone'           => $data['customer_phone'],
            'shipping_address'         => $data['shipping_address'],
            'customer_notes'           => $data['customer_notes'] ?? null,
            'status'                   => 'pending',
            'stripe_payment_intent_id' => $paymentIntentId,
        ]);

        $order->statusHistory()->create([
            'status'     => 'pending',
            'note'       => 'Store order created',
            'changed_by' => null,
        ]);

        return $order;
    }

    private function resolveVariant(StoreProduct $product, ?string $vid): ?array
    {
        $variants = $product->variants ?? [];
        if (empty($variants)) {
            return null;
        }

        if (filled($vid)) {
            foreach ($variants as $variant) {
                if (($variant['vid'] ?? null) === $vid) {
                    return $variant;
                }
            }
        }

        return $variants[0] ?? null;
    }

    /**
     * @return array{0: float, 1: ?string, 2: int}
     */
    private function resolvePricingDetails(StoreProduct $product, ?string $requestedVid): array
    {
        $variant = $this->resolveVariant($product, $requestedVid);
        $unitPrice = (float) ($variant['retail_price'] ?? $product->retail_price);
        $vid = $variant['vid'] ?? $product->cj_vid;
        $weightGrams = (int) ($variant['weight_grams'] ?? $product->weight_grams ?: 500);

        return [$unitPrice, $vid, $weightGrams];
    }

    private function verifyShippingCost(
        StoreProduct $product,
        ?string $vid,
        int $quantity,
        string $country,
        string $logisticName,
        float $submittedCost
    ): float {
        $options = $this->cjService->calculateStoreShippingOptions(
            $product,
            $country,
            $vid,
            $quantity
        );

        return $this->matchShippingOptionCost($options, $logisticName, $submittedCost);
    }

    /**
     * @param  array<int, array{logisticName: string, logisticPrice: float, logisticAging?: string}>  $options
     */
    private function matchShippingOptionCost(array $options, string $logisticName, float $submittedCost): float
    {
        foreach ($options as $option) {
            if (strcasecmp($option['logisticName'], $logisticName) === 0) {
                return round((float) $option['logisticPrice'], 2);
            }
        }

        return round(max(0, $submittedCost), 2);
    }

    private function validateCartCheckoutRequest(Request $request, bool $requiresPaymentMethod): array
    {
        $rules = [
            'logistic_name'                => 'required|string|max:191',
            'shipping_cost'                => 'required|numeric|min:0|max:99999',
            'customer_name'                => 'required|string|max:200',
            'customer_email'               => 'required|email|max:200',
            'customer_phone'               => 'required|string|max:50',
            'shipping_address'             => 'required|array',
            'shipping_address.line1'       => 'required|string|max:255',
            'shipping_address.line2'       => 'nullable|string|max:255',
            'shipping_address.city'        => 'required|string|max:100',
            'shipping_address.state'       => 'nullable|string|max:100',
            'shipping_address.postal_code' => 'required|string|max:20',
            'shipping_address.country'     => 'required|string|max:100',
            'customer_notes'               => 'nullable|string|max:1000',
        ];

        if ($requiresPaymentMethod) {
            $rules['payment_method_id'] = 'required|string|max:255';
        }

        return $request->validate($rules);
    }

    /**
     * @param  array<int, array<string, mixed>>  $hydratedItems
     */
    private function finalizeCartOrder(
        array $hydratedItems,
        array $data,
        float $shippingCost,
        float $taxAmount,
        float $totalCharged,
        string $paymentRef,
        string $cjInitialStatus,
        string $paymentStatus,
        array $paymentMeta = []
    ): JsonResponse {
        $totalSubtotal = round(collect($hydratedItems)->sum('subtotal'), 2);
        $itemCount = count($hydratedItems);
        $remainingShipping = round($shippingCost, 2);
        $remainingTax = round($taxAmount, 2);
        $orders = [];

        foreach ($hydratedItems as $index => $item) {
            /** @var StoreProduct $product */
            $product = $item['product'];
            $isLast = ($index === $itemCount - 1);

            if ($isLast) {
                $itemShipping = round(max(0, $remainingShipping), 2);
                $itemTax = round(max(0, $remainingTax), 2);
            } else {
                $ratio = $totalSubtotal > 0 ? ($item['subtotal'] / $totalSubtotal) : (1 / $itemCount);
                $itemShipping = round($shippingCost * $ratio, 2);
                $itemTax = round($taxAmount * $ratio, 2);
                $remainingShipping = round($remainingShipping - $itemShipping, 2);
                $remainingTax = round($remainingTax - $itemTax, 2);
            }

            $itemTotal = round($item['subtotal'] + $itemShipping + $itemTax, 2);

            $itemPayload = $data;
            $itemPayload['quantity'] = $item['quantity'];

            $order = $this->createStoreOrder(
                $product,
                $itemPayload,
                $item['unit_price'],
                $item['vid'],
                $item['weight_grams'],
                $itemShipping,
                $itemTax,
                $itemTotal,
                $paymentRef,
                $cjInitialStatus
            );

            if (filled($item['variant_name']) && strcasecmp($item['variant_name'], 'Standard') !== 0) {
                $order->update(['product_name' => "{$product->name} ({$item['variant_name']})"]);
            }

            Payment::create([
                'order_id'                 => $order->id,
                'stripe_payment_intent_id' => $index === 0 ? $paymentRef : "{$paymentRef}_item_{$order->id}",
                'type'                     => 'initial',
                'amount'                   => $itemTotal,
                'currency'                 => 'usd',
                'status'                   => $paymentStatus,
                'stripe_metadata'          => array_merge([
                    'cart_checkout' => true,
                    'store_product' => $product->name,
                    'variant'       => $item['variant_name'],
                ], $paymentMeta),
            ]);

            if ($this->cjService->shouldAutoFulfill() && filled($order->cj_vid)) {
                $this->cjService->fulfillOrder($order);
            }

            $orders[] = $order;
        }

        session()->forget('store_cart');

        if (! empty($orders)) {
            try {
                $orders[0]->notify(new OrderConfirmedNotification($orders[0]));
            } catch (\Throwable $e) {
                Log::warning('Notification failed during Store Cart order: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success'       => true,
            'order_numbers' => collect($orders)->pluck('order_number')->all(),
            'total_charged' => $totalCharged,
            'redirect'      => route('order.success') . '?session_id=' . $paymentRef,
        ]);
    }

    private function buildCartKey(int $productId, ?string $vid): string
    {
        return $productId . ':' . ($vid ?: 'default');
    }

    private function getCartCount(): int
    {
        return (int) collect(session('store_cart', []))->sum('quantity');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hydrateCart(): array
    {
        $rawCart = session('store_cart', []);
        if (! is_array($rawCart) || empty($rawCart)) {
            return [];
        }

        $productIds = collect($rawCart)
            ->pluck('store_product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $products = StoreProduct::where('is_active', true)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $hydrated = [];
        $validSessionCart = [];

        foreach ($rawCart as $key => $entry) {
            $productId = (int) ($entry['store_product_id'] ?? 0);
            /** @var StoreProduct|null $product */
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }

            $requestedVid = $entry['vid'] ?? null;
            $variant = $this->resolveVariant($product, $requestedVid);
            [$unitPrice, $vid, $weightGrams] = $this->resolvePricingDetails($product, $requestedVid);
            $quantity = max(1, min(100, (int) ($entry['quantity'] ?? 1)));

            $validSessionCart[$key] = [
                'store_product_id' => $product->id,
                'vid'              => $vid,
                'quantity'         => $quantity,
            ];

            $hydrated[] = [
                'key'          => (string) $key,
                'product'      => $product,
                'product_id'   => $product->id,
                'name'         => $product->name,
                'slug'         => $product->slug,
                'vid'          => $vid,
                'variant_name' => (string) ($variant['name'] ?? $variant['key'] ?? 'Standard'),
                'image'        => $variant['image'] ?? $product->display_image_url,
                'unit_price'   => round($unitPrice, 2),
                'quantity'     => $quantity,
                'subtotal'     => round($unitPrice * $quantity, 2),
                'weight_grams' => $weightGrams,
                'url'          => route('store.show', $product),
            ];
        }

        if (count($validSessionCart) !== count($rawCart)) {
            session(['store_cart' => $validSessionCart]);
        }

        return $hydrated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $hydrated
     * @return array<int, array<string, mixed>>
     */
    private function serializeCartItems(array $hydrated): array
    {
        return collect($hydrated)
            ->map(function (array $item) {
                unset($item['product'], $item['weight_grams']);

                return $item;
            })
            ->values()
            ->all();
    }

    private function buildCartSummaryResponse(array $extra = []): JsonResponse
    {
        $hydrated = $this->hydrateCart();
        $subtotal = round(collect($hydrated)->sum('subtotal'), 2);
        $taxAmount = ! empty($hydrated) ? $this->cjService->calculateCartTaxAmount($hydrated) : 0.0;

        return response()->json(array_merge([
            'success'    => true,
            'cart_count' => $this->getCartCount(),
            'items'      => $this->serializeCartItems($hydrated),
            'subtotal'   => $subtotal,
            'tax_amount' => $taxAmount,
            'cart_url'   => route('store.cart'),
        ], $extra));
    }
}
