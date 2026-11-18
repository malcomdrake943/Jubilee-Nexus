<?php

namespace App\Services;

use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StoreProduct;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class CjDropshippingService
{
    private const CACHE_TOKEN_KEY = 'cj_access_token';

    public function getBaseUrl(): string
    {
        return rtrim((string) config('services.cjdropshipping.base_url', 'https://developers.cjdropshipping.com/api2.0/v1'), '/');
    }

    public function getApiKey(): ?string
    {
        $configKey = config('services.cjdropshipping.api_key');

        return filled($configKey) ? $configKey : Setting::get('cj_api_key');
    }

    public function getApiSecret(): ?string
    {
        $configSecret = config('services.cjdropshipping.api_secret');

        return filled($configSecret) ? $configSecret : Setting::get('cj_api_secret');
    }

    public function getDefaultMarkupPercent(): float
    {
        $setting = Setting::get('cj_markup_percent');

        return is_numeric($setting)
            ? (float) $setting
            : (float) config('services.cjdropshipping.markup_percent', 25);
    }

    public function getShippingMarkupPercent(): float
    {
        $setting = Setting::get('store_shipping_markup_percent');

        return is_numeric($setting) ? (float) $setting : 0.0;
    }

    public function getShippingFlatFee(): float
    {
        $setting = Setting::get('store_shipping_flat_fee');

        return is_numeric($setting) ? (float) $setting : 0.0;
    }

    public function getStoreTaxPercent(): float
    {
        $setting = Setting::get('store_tax_percent');

        return is_numeric($setting) ? (float) $setting : 0.0;
    }

    public function getStoreTaxFlat(): float
    {
        $setting = Setting::get('store_tax_flat');

        return is_numeric($setting) ? (float) $setting : 0.0;
    }

    public function applyShippingRules(float $basePrice): float
    {
        $markupPercent = $this->getShippingMarkupPercent();
        $flatFee = $this->getShippingFlatFee();

        return round(max(0, ($basePrice * (1 + ($markupPercent / 100))) + $flatFee), 2);
    }

    public function shouldAutoFulfill(): bool
    {
        $setting = Setting::get('cj_auto_fulfill');
        if ($setting !== null) {
            return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('services.cjdropshipping.auto_fulfill', true);
    }

    public function shouldAutoPay(): bool
    {
        $setting = Setting::get('cj_auto_pay');
        if ($setting !== null) {
            return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('services.cjdropshipping.auto_pay', true);
    }

    public function isConfigured(): bool
    {
        return filled($this->getApiKey());
    }

    /**
     * Retrieve a valid CJdropshipping Access Token using cache, Settings, refreshToken, or apiKey.
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh) {
            $cached = Cache::get(self::CACHE_TOKEN_KEY);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $storedToken = Setting::get('cj_access_token');
            $expiresAt = Setting::get('cj_access_token_expires_at');
            if ($storedToken && $expiresAt && Carbon::parse($expiresAt)->subMinutes(10)->isFuture()) {
                Cache::put(self::CACHE_TOKEN_KEY, $storedToken, Carbon::parse($expiresAt)->subMinutes(10));

                return $storedToken;
            }

            $refreshToken = Setting::get('cj_refresh_token');
            $refreshExpiresAt = Setting::get('cj_refresh_token_expires_at');
            if ($refreshToken && (! $refreshExpiresAt || Carbon::parse($refreshExpiresAt)->subMinutes(10)->isFuture())) {
                try {
                    return $this->refreshAccessToken($refreshToken);
                } catch (\Throwable $e) {
                    Log::warning('CJ refreshAccessToken failed, falling back to getAccessToken', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $apiKey = $this->getApiKey();
        if (blank($apiKey)) {
            throw new RuntimeException('CJdropshipping API key is not configured.');
        }

        $response = $this->baseClient()
            ->post('/authentication/getAccessToken', [
                'apiKey' => $apiKey,
            ]);

        $data = $this->unwrapResponse($response->json(), 'Authentication failed');

        return $this->persistTokenPayload($data);
    }

    public function refreshAccessToken(string $refreshToken): string
    {
        $response = $this->baseClient()
            ->post('/authentication/refreshAccessToken', [
                'refreshToken' => $refreshToken,
            ]);

        $data = $this->unwrapResponse($response->json(), 'Token refresh failed');

        return $this->persistTokenPayload($data);
    }

    /**
     * Search CJdropshipping global catalog (product/listV2).
     */
    public function searchCatalog(string $keyword = '', int $page = 1, int $size = 20, ?string $categoryId = null): array
    {
        $query = array_filter([
            'page'       => max(1, $page),
            'size'       => min(100, max(1, $size)),
            'keyWord'    => $keyword !== '' ? $keyword : null,
            'categoryId' => $categoryId,
        ], fn ($v) => $v !== null);

        $response = $this->authorizedClient()->get('/product/listV2', $query);
        $data = $this->unwrapResponse($response->json(), 'Catalog search failed');

        return $data['content'][0]['productList']
            ?? $data['list']
            ?? (is_array($data) ? $data : []);
    }

    /**
     * Fetch products added to the merchant's CJ "My Products" list.
     */
    public function getMyProducts(string $keyword = '', int $page = 1, int $size = 20): array
    {
        $query = array_filter([
            'pageNum'  => max(1, $page),
            'pageSize' => min(100, max(1, $size)),
            'keyword'  => $keyword !== '' ? $keyword : null,
        ], fn ($v) => $v !== null);

        $response = $this->authorizedClient()->get('/product/myProduct/query', $query);
        $data = $this->unwrapResponse($response->json(), 'Fetching CJ My Products failed');

        return $data['content']
            ?? $data['list']
            ?? (is_array($data) ? $data : []);
    }

    /**
     * Fetch full product details & variants by CJ product ID (pid).
     */
    public function getProductByPid(string $pid): ?array
    {
        $response = $this->authorizedClient()->get('/product/query', [
            'pid' => $pid,
        ]);

        $data = $this->unwrapResponse($response->json(), "Unable to fetch CJ product {$pid}");

        return is_array($data) ? $data : null;
    }

    /**
     * Import or update a CJdropshipping product in the local store_products table.
     */
    public function importProductByPid(string $pid, ?float $markupPercent = null): StoreProduct
    {
        $raw = $this->getProductByPid($pid);
        if (! $raw) {
            throw new RuntimeException("CJ product {$pid} returned empty data.");
        }

        $name = (string) ($raw['productNameEn'] ?? $raw['productName'] ?? $raw['nameEn'] ?? "CJ Product {$pid}");
        $images = $this->extractImageUrls($raw['productImage'] ?? null, $raw['productImageSet'] ?? []);
        $mainImage = $images[0] ?? null;

        $variants = [];
        foreach ((array) ($raw['variants'] ?? []) as $variant) {
            $variantSellPrice = $this->parseNumericPrice($variant['variantSellPrice'] ?? $raw['sellPrice'] ?? 0);
            $variants[] = [
                'vid'           => $variant['vid'] ?? null,
                'sku'           => $variant['variantSku'] ?? null,
                'name'          => $variant['variantNameEn'] ?? $variant['variantKey'] ?? 'Standard',
                'key'           => $variant['variantKey'] ?? ($variant['variantNameEn'] ?? 'Default'),
                'cj_price'      => $variantSellPrice,
                'retail_price'  => StoreProduct::calculateRetailPrice($variantSellPrice, $markupPercent),
                'weight_grams'  => $this->parseWeightGrams($variant['variantWeight'] ?? $raw['productWeight'] ?? 500),
                'image'         => $variant['variantImage'] ?? $mainImage,
            ];
        }

        $baseCost = $this->parseNumericPrice($raw['sellPrice'] ?? ($variants[0]['cj_price'] ?? 0));
        $primaryVid = $variants[0]['vid'] ?? ($raw['vid'] ?? null);
        $weightGrams = $this->parseWeightGrams($raw['productWeight'] ?? ($variants[0]['weight_grams'] ?? 500));

        $existing = StoreProduct::where('cj_pid', $pid)->first();
        $slug = $existing?->slug ?: $this->generateUniqueSlug($name);

        return StoreProduct::updateOrCreate(
            ['cj_pid' => $pid],
            [
                'cj_vid'              => $primaryVid,
                'sku'                 => $raw['productSku'] ?? ($variants[0]['sku'] ?? null),
                'name'                => $name,
                'slug'                => $slug,
                'category_name'       => $raw['categoryName'] ?? 'General',
                'cj_category_id'      => $raw['categoryId'] ?? null,
                'description'         => $raw['description'] ?? null,
                'image_url'           => $mainImage,
                'gallery_images'      => $images,
                'variants'            => $variants,
                'cj_cost_price'       => $baseCost,
                'retail_price'        => StoreProduct::calculateRetailPrice($baseCost, $markupPercent),
                'weight_grams'        => $weightGrams,
                'source_country_code' => 'CN',
                'is_active'           => true,
            ]
        );
    }

    /**
     * Calculate shipping methods & costs via CJ Logistics API, falling back to local delivery options.
     *
     * @return array<int, array{logisticName: string, logisticPrice: float, logisticAging: string}>
     */
    public function calculateFreight(
        string $endCountryCode,
        ?string $vid,
        int $quantity = 1,
        string $startCountryCode = 'CN'
    ): array {
        $countryCode = $this->normalizeCountryCode($endCountryCode);

        if ($this->isConfigured() && filled($vid)) {
            try {
                $response = $this->authorizedClient()->post('/logistic/freightCalculate', [
                    'startCountryCode' => strtoupper($startCountryCode),
                    'endCountryCode'   => $countryCode,
                    'products'         => [
                        [
                            'vid'      => $vid,
                            'quantity' => max(1, $quantity),
                        ],
                    ],
                ]);

                $data = $this->unwrapResponse($response->json(), 'Freight calculation failed');
                if (is_array($data) && count($data) > 0) {
                    return collect($data)
                        ->filter(fn ($opt) => is_array($opt) && filled($opt['logisticName'] ?? null))
                        ->map(fn ($opt) => [
                            'logisticName'  => (string) $opt['logisticName'],
                            'logisticPrice' => $this->applyShippingRules((float) ($opt['logisticPrice'] ?? 0)),
                            'logisticAging' => (string) ($opt['logisticAging'] ?? '7-15 Days'),
                        ])
                        ->sortBy('logisticPrice')
                        ->values()
                        ->all();
                }
            } catch (\Throwable $e) {
                Log::warning('CJ freight calculation fallback triggered', [
                    'vid'     => $vid,
                    'country' => $countryCode,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $this->fallbackDeliveryOptions();
    }

    /**
     * Calculate shipping options for a StoreProduct, honoring per-product custom_shipping_price overrides.
     *
     * @return array<int, array{logisticName: string, logisticPrice: float, logisticAging: string}>
     */
    public function calculateStoreShippingOptions(
        StoreProduct $product,
        string $country,
        ?string $vid,
        int $quantity = 1
    ): array {
        $options = $this->calculateFreight(
            $country,
            $vid,
            $quantity,
            $product->source_country_code ?: 'CN'
        );

        if ($product->custom_shipping_price !== null) {
            $customPrice = round(max(0, (float) $product->custom_shipping_price), 2);

            return collect($options)
                ->map(fn ($opt) => [
                    'logisticName'  => $opt['logisticName'],
                    'logisticPrice' => $customPrice,
                    'logisticAging' => $opt['logisticAging'],
                ])
                ->all();
        }

        return $options;
    }

    /**
     * Create, confirm, and pay (if enabled) a CJdropshipping fulfillment order.
     *
     * @return array{success: bool, cj_order_id: ?string, status: string, message: string}
     */
    public function fulfillOrder(Order $order): array
    {
        if (blank($order->cj_vid)) {
            $order->update([
                'cj_order_status'  => 'skipped',
                'cj_error_message' => 'Missing CJ Variant ID (cj_vid).',
            ]);

            return [
                'success'     => false,
                'cj_order_id' => null,
                'status'      => 'skipped',
                'message'     => 'Missing CJ Variant ID (cj_vid).',
            ];
        }

        if (! $this->isConfigured()) {
            $order->update([
                'cj_order_status'  => 'pending_config',
                'cj_error_message' => 'CJdropshipping API key is not configured.',
            ]);

            return [
                'success'     => false,
                'cj_order_id' => null,
                'status'      => 'pending_config',
                'message'     => 'CJdropshipping API key is not configured.',
            ];
        }

        $address = $order->shipping_address ?? [];
        $countryName = (string) ($address['country'] ?? 'United States');
        $countryCode = $this->normalizeCountryCode($countryName);
        $autoPay = $this->shouldAutoPay();

        $payload = [
            'orderNumber'          => $order->order_number,
            'shippingZip'          => (string) ($address['postal_code'] ?? ''),
            'shippingCountry'      => $countryName,
            'shippingCountryCode'  => $countryCode,
            'shippingProvince'     => (string) ($address['state'] ?? $address['city'] ?? ''),
            'shippingCity'         => (string) ($address['city'] ?? ''),
            'shippingPhone'        => (string) $order->customer_phone,
            'shippingCustomerName' => (string) $order->customer_name,
            'shippingAddress'      => (string) ($address['line1'] ?? ''),
            'shippingAddress2'     => (string) ($address['line2'] ?? ''),
            'email'                => (string) $order->customer_email,
            'remark'               => (string) ($order->customer_notes ?? ''),
            'fromCountryCode'      => $order->storeProduct?->source_country_code ?: 'CN',
            'logisticName'         => $order->cj_logistic_name ?: 'CJPacket Ordinary',
            'payType'              => $autoPay ? 2 : 3,
            'products'             => [
                [
                    'vid'      => $order->cj_vid,
                    'quantity' => (int) $order->quantity,
                ],
            ],
        ];

        try {
            $createResponse = $this->authorizedClient()->post('/shopping/order/createOrderV2', $payload);
            $createData = $this->unwrapResponse($createResponse->json(), 'CJ order creation failed');

            $cjOrderId = is_string($createData)
                ? $createData
                : ($createData['orderId'] ?? $createData['shipmentOrderId'] ?? null);

            if (blank($cjOrderId)) {
                throw new RuntimeException('CJ did not return an orderId.');
            }

            $status = $autoPay ? 'paid' : 'created';
            $message = $autoPay ? 'Order created and paid via CJ Wallet balance.' : 'Order created on CJdropshipping (awaiting payment).';

            // If order was created with payType=3 or requires explicit confirmation + balance payment,
            // callers can also invoke confirmAndPayOrder($order).
            $order->update([
                'cj_order_id'      => $cjOrderId,
                'cj_order_status'  => $status,
                'cj_error_message' => null,
            ]);

            if ($autoPay) {
                $order->recordStatusChange('purchased', "Auto-fulfilled on CJdropshipping (CJ Order ID: {$cjOrderId})");
            }

            return [
                'success'     => true,
                'cj_order_id' => $cjOrderId,
                'status'      => $status,
                'message'     => $message,
            ];
        } catch (\Throwable $e) {
            Log::error('CJ order fulfillment failed', [
                'order_number' => $order->order_number,
                'error'        => $e->getMessage(),
            ]);

            $order->update([
                'cj_order_status'  => 'failed',
                'cj_error_message' => $e->getMessage(),
            ]);

            return [
                'success'     => false,
                'cj_order_id' => $order->cj_order_id,
                'status'      => 'failed',
                'message'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Confirm an existing unpaid CJ order and settle via CJ Wallet balance.
     */
    public function confirmAndPayOrder(Order $order): array
    {
        if (blank($order->cj_order_id)) {
            return $this->fulfillOrder($order);
        }

        try {
            $confirmResp = $this->authorizedClient()->patch('/shopping/order/confirmOrder', [
                'orderId' => $order->cj_order_id,
            ]);
            $this->unwrapResponse($confirmResp->json(), 'CJ order confirmation failed');

            $payResp = $this->authorizedClient()->post('/shopping/pay/payBalance', [
                'orderId' => $order->cj_order_id,
            ]);
            $this->unwrapResponse($payResp->json(), 'CJ Wallet balance payment failed');

            $order->update([
                'cj_order_status'  => 'paid',
                'cj_error_message' => null,
            ]);
            $order->recordStatusChange('purchased', "Confirmed & paid on CJdropshipping (CJ Order ID: {$order->cj_order_id})");

            return [
                'success'     => true,
                'cj_order_id' => $order->cj_order_id,
                'status'      => 'paid',
                'message'     => 'Order confirmed and paid via CJ Wallet balance.',
            ];
        } catch (\Throwable $e) {
            $order->update([
                'cj_order_status'  => 'failed',
                'cj_error_message' => $e->getMessage(),
            ]);

            return [
                'success'     => false,
                'cj_order_id' => $order->cj_order_id,
                'status'      => 'failed',
                'message'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Query shipment tracking details for a tracking number.
     */
    public function getTracking(string $trackNumber): ?array
    {
        $response = $this->authorizedClient()->get('/logistic/trackInfo', [
            'trackNumber' => $trackNumber,
        ]);

        $data = $this->unwrapResponse($response->json(), 'Tracking lookup failed');

        return is_array($data) ? $data : null;
    }

    /**
     * Register CJdropshipping webhooks for order, logistics, stock, and product updates.
     */
    public function registerWebhooks(string $callbackUrl): array
    {
        $response = $this->authorizedClient()->post('/webhook/set', [
            'product'   => ['type' => 'ENABLE', 'callbackUrls' => [$callbackUrl]],
            'stock'     => ['type' => 'ENABLE', 'callbackUrls' => [$callbackUrl]],
            'order'     => ['type' => 'ENABLE', 'callbackUrls' => [$callbackUrl]],
            'logistics' => ['type' => 'ENABLE', 'callbackUrls' => [$callbackUrl]],
        ]);

        $data = $this->unwrapResponse($response->json(), 'Webhook registration failed');

        return is_array($data) ? $data : ['registered' => true];
    }

    public function normalizeCountryCode(string $country): string
    {
        $trimmed = trim($country);
        if (strlen($trimmed) === 2 && ctype_alpha($trimmed)) {
            return strtoupper($trimmed);
        }

        $map = [
            'united states'  => 'US',
            'usa'            => 'US',
            'u.s.'           => 'US',
            'u.s.a.'         => 'US',
            'united kingdom' => 'GB',
            'uk'             => 'GB',
            'great britain'  => 'GB',
            'canada'         => 'CA',
            'australia'      => 'AU',
            'germany'        => 'DE',
            'france'         => 'FR',
            'nigeria'        => 'NG',
            'ghana'          => 'GH',
            'kenya'          => 'KE',
            'south africa'   => 'ZA',
            'liberia'        => 'LR',
            'india'          => 'IN',
            'china'          => 'CN',
            'uae'            => 'AE',
            'united arab emirates' => 'AE',
        ];

        $key = strtolower($trimmed);

        return $map[$key] ?? 'US';
    }

    private function baseClient(): PendingRequest
    {
        return Http::baseUrl($this->getBaseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(25);
    }

    private function authorizedClient(): PendingRequest
    {
        return $this->baseClient()
            ->withHeaders([
                'CJ-Access-Token' => $this->getAccessToken(),
            ]);
    }

    private function persistTokenPayload(array $data): string
    {
        $accessToken = (string) ($data['accessToken'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('CJdropshipping response did not include an accessToken.');
        }

        $expiresAt = $data['accessTokenExpiryDate'] ?? now()->addDays(15)->toIso8601String();
        $refreshToken = $data['refreshToken'] ?? null;
        $refreshExpiresAt = $data['refreshTokenExpiryDate'] ?? now()->addDays(180)->toIso8601String();

        $ttl = Carbon::parse($expiresAt)->subMinutes(10);
        if ($ttl->isFuture()) {
            Cache::put(self::CACHE_TOKEN_KEY, $accessToken, $ttl);
        } else {
            Cache::put(self::CACHE_TOKEN_KEY, $accessToken, now()->addHours(12));
        }

        Setting::set('cj_access_token', $accessToken, 'CJ Access Token', 'cjdropshipping');
        Setting::set('cj_access_token_expires_at', (string) $expiresAt, 'CJ Access Token Expiry', 'cjdropshipping');
        if ($refreshToken) {
            Setting::set('cj_refresh_token', (string) $refreshToken, 'CJ Refresh Token', 'cjdropshipping');
            Setting::set('cj_refresh_token_expires_at', (string) $refreshExpiresAt, 'CJ Refresh Token Expiry', 'cjdropshipping');
        }

        return $accessToken;
    }

    private function unwrapResponse(?array $json, string $fallbackError): mixed
    {
        if (! is_array($json)) {
            throw new RuntimeException("{$fallbackError}: Invalid JSON response from CJdropshipping.");
        }

        $code = $json['code'] ?? null;
        $result = $json['result'] ?? false;

        if ((int) $code !== 200 || $result === false) {
            $message = $json['message'] ?? $fallbackError;
            throw new RuntimeException("CJ API Error ({$code}): {$message}");
        }

        return $json['data'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    private function extractImageUrls(mixed $primary, mixed $gallery): array
    {
        $urls = [];

        if (is_string($primary) && $primary !== '') {
            $decoded = json_decode($primary, true);
            if (is_array($decoded)) {
                $urls = array_merge($urls, $decoded);
            } else {
                $urls = array_merge($urls, array_map('trim', explode(',', $primary)));
            }
        } elseif (is_array($primary)) {
            $urls = array_merge($urls, $primary);
        }

        if (is_string($gallery) && $gallery !== '') {
            $decoded = json_decode($gallery, true);
            if (is_array($decoded)) {
                $urls = array_merge($urls, $decoded);
            }
        } elseif (is_array($gallery)) {
            $urls = array_merge($urls, $gallery);
        }

        return collect($urls)
            ->filter(fn ($u) => is_string($u) && trim($u) !== '')
            ->map(fn ($u) => trim($u))
            ->unique()
            ->values()
            ->all();
    }

    private function parseNumericPrice(mixed $raw): float
    {
        if (is_numeric($raw)) {
            return round((float) $raw, 2);
        }

        if (is_string($raw) && preg_match('/([0-9]+(?:\.[0-9]+)?)/', $raw, $m)) {
            return round((float) $m[1], 2);
        }

        return 0.0;
    }

    private function parseWeightGrams(mixed $raw): int
    {
        if (is_numeric($raw)) {
            return max(1, (int) round((float) $raw));
        }

        if (is_string($raw) && preg_match('/([0-9]+(?:\.[0-9]+)?)/', $raw, $m)) {
            return max(1, (int) round((float) $m[1]));
        }

        return 500;
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'cj-product';
        $slug = $base;
        $counter = 2;

        while (StoreProduct::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<int, array{logisticName: string, logisticPrice: float, logisticAging: string}>
     */
    private function fallbackDeliveryOptions(): array
    {
        if (Schema::hasTable('delivery_options')) {
            $options = DeliveryOption::where('is_active', true)->get();
            if ($options->isNotEmpty()) {
                return $options->map(fn ($opt) => [
                    'logisticName'  => (string) $opt->name,
                    'logisticPrice' => $this->applyShippingRules((float) ($opt->price ?? 0)),
                    'logisticAging' => "{$opt->duration} Days",
                ])->values()->all();
            }
        }

        return [
            [
                'logisticName'  => 'CJPacket Ordinary',
                'logisticPrice' => $this->applyShippingRules(0.00),
                'logisticAging' => '7-15 Days',
            ],
        ];
    }
}
