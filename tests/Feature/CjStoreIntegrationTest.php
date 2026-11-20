<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageCjSettings;
use App\Filament\Resources\StoreProductResource\Pages\CreateStoreProduct;
use App\Filament\Resources\StoreProductResource\Pages\EditStoreProduct;
use App\Filament\Resources\StoreProductResource\Pages\ListStoreProducts;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StoreProduct;
use App\Models\User;
use App\Services\CjDropshippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Tests\TestCase;

class CjStoreIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Notification::fake();
    }

    public function test_store_tab_and_product_detail_pages_render_active_products(): void
    {
        $active = StoreProduct::create([
            'cj_pid'        => 'PID-100',
            'cj_vid'        => 'VID-100',
            'name'          => 'Wireless Noise Cancelling Earbuds',
            'category_name' => 'Electronics',
            'image_url'     => 'https://cdn.example.com/earbuds.jpg',
            'cj_cost_price' => 20.00,
            'retail_price'  => 29.99,
            'is_featured'   => true,
            'is_active'     => true,
        ]);

        $inactive = StoreProduct::create([
            'cj_pid'       => 'PID-200',
            'name'         => 'Hidden Draft Item',
            'cj_cost_price'=> 10.00,
            'retail_price' => 15.00,
            'is_active'    => false,
        ]);

        $response = $this->get('/store');
        $response->assertOk();
        $response->assertSee('Wireless Noise Cancelling Earbuds');
        $response->assertSee('$29.99');
        $response->assertDontSee('Hidden Draft Item');

        $detail = $this->get(route('store.show', $active));
        $detail->assertOk();
        $detail->assertSee('Wireless Noise Cancelling Earbuds');

        $this->get(route('store.show', $inactive))->assertNotFound();
    }

    public function test_cj_service_authenticates_imports_product_calculates_freight_and_fulfills_order(): void
    {
        Setting::set('cj_api_key', 'CJUser@api@testkey');
        Setting::set('cj_markup_percent', '50');

        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'code'    => 200,
                'result'  => true,
                'message' => 'Success',
                'data'    => [
                    'accessToken'            => 'cj-token-12345',
                    'accessTokenExpiryDate'  => now()->addDays(15)->toIso8601String(),
                    'refreshToken'           => 'cj-refresh-12345',
                    'refreshTokenExpiryDate' => now()->addDays(180)->toIso8601String(),
                ],
            ], 200),
            '*/product/query*' => Http::response([
                'code'    => 200,
                'result'  => true,
                'message' => 'Success',
                'data'    => [
                    'pid'            => 'CJ-PID-999',
                    'productNameEn'  => 'Smart Coffee Warmer',
                    'productSku'     => 'CJSKU-999',
                    'categoryName'   => 'Home & Kitchen',
                    'categoryId'     => 'CAT-1',
                    'description'    => 'Keeps drinks warm.',
                    'productImage'   => 'https://cdn.example.com/warmer.jpg',
                    'productWeight'  => 350,
                    'sellPrice'      => 12.00,
                    'variants'       => [
                        [
                            'vid'              => 'CJ-VID-999-A',
                            'variantSku'       => 'CJSKU-999-BLK',
                            'variantNameEn'    => 'Matte Black',
                            'variantKey'       => 'Black',
                            'variantSellPrice' => 12.00,
                            'variantWeight'    => 350,
                            'variantImage'     => 'https://cdn.example.com/warmer-black.jpg',
                        ],
                    ],
                ],
            ], 200),
            '*/logistic/freightCalculate' => Http::response([
                'code'    => 200,
                'result'  => true,
                'message' => 'Success',
                'data'    => [
                    [
                        'logisticName'  => 'CJPacket Ordinary',
                        'logisticPrice' => 6.50,
                        'logisticAging' => '7-12 Days',
                    ],
                    [
                        'logisticName'  => 'DHL Express',
                        'logisticPrice' => 22.00,
                        'logisticAging' => '3-5 Days',
                    ],
                ],
            ], 200),
            '*/shopping/order/createOrderV2' => Http::response([
                'code'    => 200,
                'result'  => true,
                'message' => 'Success',
                'data'    => [
                    'orderId' => 'CJ-ORD-777888',
                ],
            ], 200),
        ]);

        /** @var CjDropshippingService $service */
        $service = app(CjDropshippingService::class);

        $product = $service->importProductByPid('CJ-PID-999');
        $this->assertSame('Smart Coffee Warmer', $product->name);
        $this->assertSame('CJ-VID-999-A', $product->cj_vid);
        $this->assertEquals(18.00, (float) $product->retail_price);

        $shippingResponse = $this->postJson(route('store.shipping'), [
            'store_product_id' => $product->id,
            'vid'              => 'CJ-VID-999-A',
            'quantity'         => 2,
            'country'          => 'United States',
        ]);

        $shippingResponse->assertOk()
            ->assertJsonPath('unit_price', 18)
            ->assertJsonPath('subtotal', 36)
            ->assertJsonPath('shipping_options.0.logisticName', 'CJPacket Ordinary')
            ->assertJsonPath('shipping_options.0.logisticPrice', 6.5);

        $checkoutResponse = $this->postJson(route('store.checkout.card'), [
            'store_product_id'  => $product->id,
            'vid'               => 'CJ-VID-999-A',
            'quantity'          => 2,
            'logistic_name'     => 'CJPacket Ordinary',
            'shipping_cost'     => 6.50,
            'payment_method_id' => 'pm_mock_store_test',
            'customer_name'     => 'Alice Shopper',
            'customer_email'    => 'alice@example.com',
            'customer_phone'    => '+15551234567',
            'shipping_address'  => [
                'line1'       => '500 Market St',
                'city'        => 'San Francisco',
                'state'       => 'CA',
                'postal_code' => '94105',
                'country'     => 'United States',
            ],
        ]);

        $checkoutResponse->assertOk()->assertJson(['success' => true]);

        $order = Order::where('customer_email', 'alice@example.com')->first();
        $this->assertNotNull($order);
        $this->assertEquals(42.50, (float) $order->total_charged);
        $this->assertSame('CJ-ORD-777888', $order->cj_order_id);
        $this->assertSame('paid', $order->cj_order_status);
        $this->assertSame('purchased', $order->status);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/shopping/order/createOrderV2')
            && $request['payType'] === 2
            && $request['products'][0]['vid'] === 'CJ-VID-999-A'
            && $request['products'][0]['quantity'] === 2);
    }

    public function test_mobile_money_store_checkout_creates_order_and_auto_fulfills_when_cj_configured(): void
    {
        Setting::set('cj_api_key', 'CJUser@api@mmkey');

        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    'accessToken'           => 'cj-token-mm',
                    'accessTokenExpiryDate' => now()->addDays(15)->toIso8601String(),
                ],
            ], 200),
            '*/logistic/freightCalculate' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    [
                        'logisticName'  => 'CJPacket Ordinary',
                        'logisticPrice' => 5.00,
                        'logisticAging' => '7-12 Days',
                    ],
                ],
            ], 200),
            '*/shopping/order/createOrderV2' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    'orderId' => 'CJ-MM-ORD-101',
                ],
            ], 200),
        ]);

        $product = StoreProduct::create([
            'cj_pid'        => 'PID-MM-1',
            'cj_vid'        => 'VID-MM-1',
            'name'          => 'LED Desk Lamp',
            'cj_cost_price' => 10.00,
            'retail_price'  => 20.00,
            'is_active'     => true,
        ]);

        $response = $this->postJson(route('store.checkout.mobile-money'), [
            'store_product_id' => $product->id,
            'vid'              => 'VID-MM-1',
            'quantity'         => 1,
            'logistic_name'    => 'CJPacket Ordinary',
            'shipping_cost'    => 5.00,
            'customer_name'    => 'Bob Buyer',
            'customer_email'   => 'bob@example.com',
            'customer_phone'   => '+231555000111',
            'shipping_address' => [
                'line1'       => '10 Broad St',
                'city'        => 'Monrovia',
                'postal_code' => '1000',
                'country'     => 'Liberia',
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $order = Order::where('customer_email', 'bob@example.com')->first();
        $this->assertNotNull($order);
        $this->assertEquals(25.00, (float) $order->total_charged);
        $this->assertSame('CJ-MM-ORD-101', $order->cj_order_id);
        $this->assertSame('paid', $order->cj_order_status);
        $this->assertSame('purchased', $order->status);
    }

    public function test_cj_webhook_updates_order_tracking_and_product_stock(): void
    {
        $product = StoreProduct::create([
            'cj_pid'        => 'PID-WH-1',
            'cj_vid'        => 'VID-WH-1',
            'name'          => 'Travel Backpack',
            'cj_cost_price' => 15.00,
            'retail_price'  => 35.00,
            'stock'         => 50,
            'is_active'     => true,
        ]);

        $order = Order::create([
            'store_product_id'        => $product->id,
            'cj_pid'                  => 'PID-WH-1',
            'cj_vid'                  => 'VID-WH-1',
            'cj_order_id'             => 'CJ-ORD-WH-1',
            'cj_order_status'         => 'paid',
            'order_number'            => 'PP-8899',
            'product_url'             => 'https://jubileedirect.net/store',
            'product_name'            => 'Travel Backpack',
            'source_platform'         => 'other',
            'quantity'                => 1,
            'size_tier'               => 'small',
            'estimated_product_price' => 35.00,
            'service_fee'             => 0,
            'size_handling_fee'       => 5.00,
            'total_charged'           => 40.00,
            'customer_name'           => 'Jane Doe',
            'customer_email'          => 'jane@example.com',
            'customer_phone'          => '+15550001111',
            'shipping_address'        => ['line1' => '1 Main St', 'city' => 'NY', 'postal_code' => '10001', 'country' => 'US'],
            'status'                  => 'purchased',
        ]);

        $this->postJson(route('webhooks.cjdropshipping'), [
            'type'   => 'LOGISTICS',
            'params' => [
                'orderId'      => 'CJ-ORD-WH-1',
                'orderNumber'  => 'PP-8899',
                'trackNumber'  => 'CJTRK987654321',
                'logisticName' => 'CJPacket Ordinary',
                'orderStatus'  => 'SHIPPED',
            ],
        ])->assertOk()->assertJson(['status' => 'ok']);

        $order->refresh();
        $this->assertSame('CJTRK987654321', $order->tracking_number);
        $this->assertSame('CJPacket Ordinary', $order->tracking_carrier);
        $this->assertSame('shipped', $order->status);

        $this->postJson(route('webhooks.cjdropshipping'), [
            'type'   => 'STOCK',
            'params' => [
                'pid'        => 'PID-WH-1',
                'storageNum' => 17,
            ],
        ])->assertOk();

        $this->assertSame(17, $product->fresh()->stock);
    }

    public function test_superadmin_can_save_cj_settings_and_import_product_via_filament(): void
    {
        $superAdmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($superAdmin);

        config([
            'services.cjdropshipping.api_key'    => 'CJUser@api@savedkey',
            'services.cjdropshipping.api_secret' => 'secret-123',
        ]);

        Livewire::test(ManageCjSettings::class)
            ->fillForm([
                'cj_markup_percent'             => 30,
                'store_shipping_markup_percent' => 15,
                'store_shipping_flat_fee'       => 3.00,
                'store_tax_percent'             => 8,
                'store_tax_flat'                => 1.50,
                'cj_auto_fulfill'               => true,
                'cj_auto_pay'                   => true,
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertSame('30', Setting::get('cj_markup_percent'));
        $this->assertSame('15', Setting::get('store_shipping_markup_percent'));
        $this->assertSame('3', Setting::get('store_shipping_flat_fee'));
        $this->assertSame('8', Setting::get('store_tax_percent'));
        $this->assertSame('1.5', Setting::get('store_tax_flat'));

        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    'accessToken'           => 'cj-token-admin',
                    'accessTokenExpiryDate' => now()->addDays(15)->toIso8601String(),
                ],
            ], 200),
            '*/product/query*' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    'pid'           => 'ADMIN-PID-1',
                    'productNameEn' => 'Admin Imported Mug',
                    'sellPrice'     => 10.00,
                    'variants'      => [
                        [
                            'vid'              => 'ADMIN-VID-1',
                            'variantSellPrice' => 10.00,
                            'variantNameEn'    => 'White',
                        ],
                    ],
                ],
            ], 200),
        ]);

        Livewire::test(CreateStoreProduct::class)
            ->fillForm([
                'name'          => 'Manual Store Gadget',
                'category_name' => 'Gadgets',
                'cj_cost_price' => 15.00,
                'retail_price'  => 24.99,
                'is_active'     => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = StoreProduct::where('name', 'Manual Store Gadget')->first();
        $this->assertNotNull($created);

        Livewire::test(EditStoreProduct::class, ['record' => $created->getRouteKey()])
            ->assertSuccessful();

        $imported = app(CjDropshippingService::class)->importProductByPid('ADMIN-PID-1', 40);
        $this->assertNotNull($imported);
        $this->assertEquals(14.00, (float) $imported->retail_price);
    }

    public function test_admin_custom_item_shipping_and_tax_prices_apply_at_store_checkout(): void
    {
        $superAdmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($superAdmin);

        DeliveryOption::create([
            'name'      => 'Express Air',
            'duration'  => '3-5',
            'price'     => 12.00,
            'is_active' => true,
        ]);

        config(['services.cjdropshipping.api_key' => 'CJUser@api@pricing']);

        // Set global shipping markup (20% + $2) and global tax (10% + $1)
        Livewire::test(ManageCjSettings::class)
            ->fillForm([
                'cj_markup_percent'             => 25,
                'store_shipping_markup_percent' => 20,
                'store_shipping_flat_fee'       => 2.00,
                'store_tax_percent'             => 10,
                'store_tax_flat'                => 1.00,
                'cj_auto_fulfill'               => true,
                'cj_auto_pay'                   => true,
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $product = StoreProduct::create([
            'cj_pid'        => 'PID-CUSTOM-1',
            'cj_vid'        => 'VID-CUSTOM-1',
            'name'          => 'Adjustable Desk Stand',
            'cj_cost_price' => 20.00,
            'retail_price'  => 25.00,
            'variants'      => [
                [
                    'vid'          => 'VID-CUSTOM-1',
                    'name'         => 'Silver',
                    'cj_price'     => 20.00,
                    'retail_price' => 25.00,
                    'weight_grams' => 800,
                ],
            ],
            'is_active' => true,
        ]);

        // Admin edits item price (25 -> 40), custom shipping ($9.50), and custom tax (5%)
        Livewire::test(EditStoreProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([
                'retail_price'          => 40.00,
                'custom_shipping_price' => 9.50,
                'tax_percent'           => 5,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();
        $this->assertEquals(40.00, (float) $product->retail_price);
        // Single variant retail_price synced automatically when admin updated item retail_price
        $this->assertEquals(40.00, (float) $product->variants[0]['retail_price']);

        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    'accessToken'           => 'cj-token-custom',
                    'accessTokenExpiryDate' => now()->addDays(15)->toIso8601String(),
                ],
            ], 200),
            '*/logistic/freightCalculate' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    [
                        'logisticName'  => 'CJPacket Ordinary',
                        'logisticPrice' => 10.00,
                        'logisticAging' => '7-12 Days',
                    ],
                ],
            ], 200),
            '*/shopping/order/createOrderV2' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    'orderId' => 'CJ-CUSTOM-ORD-1',
                ],
            ], 200),
        ]);

        // Quote endpoint reflects admin item price ($40 x 2 = $80), custom shipping ($9.50), and custom tax (5% of $80 = $4.00)
        $quote = $this->postJson(route('store.shipping'), [
            'store_product_id' => $product->id,
            'vid'              => 'VID-CUSTOM-1',
            'quantity'         => 2,
            'country'          => 'United States',
        ]);

        $quote->assertOk()
            ->assertJsonPath('unit_price', 40)
            ->assertJsonPath('subtotal', 80)
            ->assertJsonPath('tax_amount', 4)
            ->assertJsonPath('shipping_options.0.logisticPrice', 9.5);

        $checkout = $this->postJson(route('store.checkout.card'), [
            'store_product_id'  => $product->id,
            'vid'               => 'VID-CUSTOM-1',
            'quantity'          => 2,
            'logistic_name'     => 'CJPacket Ordinary',
            'shipping_cost'     => 9.50,
            'payment_method_id' => 'pm_mock_custom',
            'customer_name'     => 'Clara Customer',
            'customer_email'    => 'clara@example.com',
            'customer_phone'    => '+15559990000',
            'shipping_address'  => [
                'line1'       => '200 Pine St',
                'city'        => 'Seattle',
                'state'       => 'WA',
                'postal_code' => '98101',
                'country'     => 'United States',
            ],
        ]);

        $checkout->assertOk()->assertJson(['success' => true]);

        $order = Order::where('customer_email', 'clara@example.com')->first();
        $this->assertNotNull($order);
        $this->assertEquals(9.50, (float) $order->cj_shipping_cost);
        $this->assertEquals(4.00, (float) $order->tax_amount);
        $this->assertEquals(93.50, (float) $order->total_charged);
        $this->assertSame('CJ-CUSTOM-ORD-1', $order->cj_order_id);
        $this->assertSame('paid', $order->cj_order_status);
        $this->assertSame('purchased', $order->status);

        // Now clear product-level overrides so global shipping (+20% + $2 on $10 = $14) and global tax (10% of $40 + $1 = $5) apply
        $product->update([
            'custom_shipping_price' => null,
            'tax_percent'           => null,
        ]);

        $globalQuote = $this->postJson(route('store.shipping'), [
            'store_product_id' => $product->id,
            'vid'              => 'VID-CUSTOM-1',
            'quantity'         => 1,
            'country'          => 'United States',
        ]);

        $globalQuote->assertOk()
            ->assertJsonPath('subtotal', 40)
            ->assertJsonPath('tax_amount', 5)
            ->assertJsonPath('shipping_options.0.logisticPrice', 14);
    }

    public function test_store_products_admin_index_and_number_helpers_work_without_intl(): void
    {
        $this->assertSame('1,234.56', Number::format(1234.56, 2));
        $this->assertSame('1,234.5', Number::format(1234.50, maxPrecision: 2));
        $this->assertSame('1,234', Number::format(1234));
        $this->assertSame('$25.99', Number::currency(25.99, 'USD'));
        $this->assertSame('1st', Number::ordinal(1));
        $this->assertSame('12.5%', Number::percentage(12.5, maxPrecision: 2));

        $superAdmin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($superAdmin);

        StoreProduct::create([
            'cj_pid'        => 'PID-LIST-1',
            'name'          => 'Table Render Check Product',
            'category_name' => 'Electronics',
            'cj_cost_price' => 10.00,
            'retail_price'  => 19.99,
            'is_active'     => true,
        ]);

        Livewire::test(ListStoreProducts::class)
            ->assertSuccessful()
            ->assertSee('Table Render Check Product');

        $this->get('/admin/store-products')
            ->assertOk()
            ->assertSee('Table Render Check Product');
    }

    public function test_shopping_cart_add_update_remove_shipping_and_multi_item_checkout_fulfills_all_orders(): void
    {
        Setting::set('cj_api_key', 'CJUser@api@cartkey');
        Setting::set('store_tax_percent', '10');
        Setting::set('store_tax_flat', '2.00');
        Setting::set('cj_auto_fulfill', '1');
        Setting::set('cj_auto_pay', '1');

        Http::fake([
            '*/authentication/getAccessToken' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    'accessToken'           => 'cj-token-cart',
                    'accessTokenExpiryDate' => now()->addDays(15)->toIso8601String(),
                ],
            ], 200),
            '*/logistic/freightCalculate' => Http::response([
                'code'   => 200,
                'result' => true,
                'data'   => [
                    [
                        'logisticName'  => 'CJPacket Ordinary',
                        'logisticPrice' => 11.00,
                        'logisticAging' => '7-12 Days',
                    ],
                ],
            ], 200),
            '*/shopping/order/createOrderV2' => Http::sequence()
                ->push([
                    'code'   => 200,
                    'result' => true,
                    'data'   => ['orderId' => 'CJ-CART-ORD-1'],
                ], 200)
                ->push([
                    'code'   => 200,
                    'result' => true,
                    'data'   => ['orderId' => 'CJ-CART-ORD-2'],
                ], 200),
        ]);

        $earbuds = StoreProduct::create([
            'cj_pid'        => 'PID-CART-1',
            'cj_vid'        => 'VID-CART-1A',
            'name'          => 'Pro Wireless Earbuds',
            'cj_cost_price' => 12.00,
            'retail_price'  => 20.00,
            'variants'      => [
                ['vid' => 'VID-CART-1A', 'name' => 'Matte Black', 'retail_price' => 20.00, 'weight_grams' => 200],
                ['vid' => 'VID-CART-1B', 'name' => 'Snow White', 'retail_price' => 22.00, 'weight_grams' => 200],
            ],
            'is_active'     => true,
        ]);

        $lamp = StoreProduct::create([
            'cj_pid'        => 'PID-CART-2',
            'cj_vid'        => 'VID-CART-2',
            'name'          => 'Foldable Desk Lamp',
            'cj_cost_price' => 15.00,
            'retail_price'  => 30.00,
            'is_active'     => true,
        ]);

        $removable = StoreProduct::create([
            'cj_pid'        => 'PID-CART-3',
            'cj_vid'        => 'VID-CART-3',
            'name'          => 'Temporary Cart Item',
            'cj_cost_price' => 5.00,
            'retail_price'  => 10.00,
            'is_active'     => true,
        ]);

        // Add variant B of earbuds (x1), lamp (x1), and temporary item (x1)
        $this->postJson(route('store.cart.add'), [
            'store_product_id' => $earbuds->id,
            'vid'              => 'VID-CART-1B',
            'quantity'         => 1,
        ])->assertOk()->assertJsonPath('cart_count', 1);

        $this->postJson(route('store.cart.add'), [
            'store_product_id' => $lamp->id,
            'quantity'         => 1,
        ])->assertOk()->assertJsonPath('cart_count', 2);

        $this->postJson(route('store.cart.add'), [
            'store_product_id' => $removable->id,
            'quantity'         => 1,
        ])->assertOk()->assertJsonPath('cart_count', 3);

        // Update earbuds variant B quantity to 2 (2 * $22 = $44)
        $this->postJson(route('store.cart.update'), [
            'key'      => "{$earbuds->id}:VID-CART-1B",
            'quantity' => 2,
        ])->assertOk()->assertJsonPath('cart_count', 4);

        // Remove temporary item (cart_count returns to 3: 2 earbuds + 1 lamp; subtotal = $44 + $30 = $74)
        $this->postJson(route('store.cart.remove'), [
            'key' => "{$removable->id}:VID-CART-3",
        ])->assertOk()
            ->assertJsonPath('cart_count', 3)
            ->assertJsonPath('subtotal', 74);

        $cartPage = $this->get(route('store.cart'));
        $cartPage->assertOk()
            ->assertSee('Pro Wireless Earbuds')
            ->assertSee('Snow White')
            ->assertSee('Foldable Desk Lamp');

        // Calculate combined cart shipping & tax ($74 subtotal -> 10% + $2 flat = $9.40 tax; CJ freight $11)
        $shippingQuote = $this->postJson(route('store.cart.shipping'), [
            'country' => 'United States',
        ]);
        $shippingQuote->assertOk()
            ->assertJsonPath('subtotal', 74)
            ->assertJsonPath('tax_amount', 9.4)
            ->assertJsonPath('shipping_options.0.logisticName', 'CJPacket Ordinary')
            ->assertJsonPath('shipping_options.0.logisticPrice', 11);

        // Complete multi-item Card Checkout ($74 + $11 + $9.40 = $94.40)
        $checkout = $this->postJson(route('store.cart.checkout.card'), [
            'logistic_name'     => 'CJPacket Ordinary',
            'shipping_cost'     => 11.00,
            'payment_method_id' => 'pm_mock_cart_test',
            'customer_name'     => 'Marcus Buyer',
            'customer_email'    => 'marcus@example.com',
            'customer_phone'    => '+15553334444',
            'shipping_address'  => [
                'line1'       => '742 Evergreen Terrace',
                'city'        => 'Springfield',
                'state'       => 'OR',
                'postal_code' => '97477',
                'country'     => 'United States',
            ],
        ]);

        $checkout->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_charged', 94.4);

        $orders = Order::where('customer_email', 'marcus@example.com')->orderBy('id')->get();
        $this->assertCount(2, $orders);
        $this->assertEquals(94.40, round((float) $orders->sum('total_charged'), 2));
        $this->assertSame('Pro Wireless Earbuds (Snow White)', $orders[0]->product_name);
        $this->assertSame('VID-CART-1B', $orders[0]->cj_vid);
        $this->assertSame(2, $orders[0]->quantity);
        $this->assertSame('CJ-CART-ORD-1', $orders[0]->cj_order_id);
        $this->assertSame('paid', $orders[0]->cj_order_status);

        $this->assertSame('Foldable Desk Lamp', $orders[1]->product_name);
        $this->assertSame('VID-CART-2', $orders[1]->cj_vid);
        $this->assertSame(1, $orders[1]->quantity);
        $this->assertSame('CJ-CART-ORD-2', $orders[1]->cj_order_id);
        $this->assertSame('paid', $orders[1]->cj_order_status);

        // Verify cart cleared and confirmation page renders both orders + combined total
        $this->assertEmpty(session('store_cart'));

        $redirectUrl = $checkout->json('redirect');
        $this->get($redirectUrl)
            ->assertOk()
            ->assertSee($orders[0]->order_number)
            ->assertSee($orders[1]->order_number)
            ->assertSee('$94.40');
    }
}
