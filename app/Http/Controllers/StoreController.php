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

        foreach ($options as $option) {
            if (strcasecmp($option['logisticName'], $logisticName) === 0) {
                return round((float) $option['logisticPrice'], 2);
            }
        }

        return round(max(0, $submittedCost), 2);
    }
}
