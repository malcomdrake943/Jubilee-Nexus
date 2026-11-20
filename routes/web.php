<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\CjWebhookController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

// ── Public Media (serves storage/app/public without requiring storage:link) ──
Route::get('/media', PublicMediaController::class)
    ->name('public.media');

Route::get('/media/{path}', PublicMediaController::class)
    ->where('path', '.*');

// ── Public Pages ──────────────────────────────────────────────────────────────
Route::get('/about', [AboutController::class, 'index'])->name('about');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

// ── E-Commerce Store (CJdropshipping) ─────────────────────────────────────────
Route::prefix('store')->name('store.')->group(function () {
    Route::get('/', [StoreController::class, 'index'])->name('index');
    Route::get('/cart', [StoreController::class, 'cart'])->name('cart');
    Route::post('/cart/add', [StoreController::class, 'addToCart'])->name('cart.add');
    Route::post('/cart/update', [StoreController::class, 'updateCart'])->name('cart.update');
    Route::post('/cart/remove', [StoreController::class, 'removeFromCart'])->name('cart.remove');
    Route::post('/cart/clear', [StoreController::class, 'clearCart'])->name('cart.clear');
    Route::post('/cart/shipping', [StoreController::class, 'calculateCartShipping'])->name('cart.shipping');
    Route::post('/cart/checkout/card', [StoreController::class, 'processCartCardCheckout'])->name('cart.checkout.card');
    Route::post('/cart/checkout/mobile-money', [StoreController::class, 'processCartMobileMoneyCheckout'])->name('cart.checkout.mobile-money');
    Route::get('/products/{storeProduct}', [StoreController::class, 'show'])->name('show');
    Route::post('/shipping', [StoreController::class, 'calculateShipping'])->name('shipping');
    Route::post('/checkout/card', [StoreController::class, 'processCardCheckout'])->name('checkout.card');
    Route::post('/checkout/mobile-money', [StoreController::class, 'processMobileMoneyCheckout'])->name('checkout.mobile-money');
});

Route::post('/webhooks/cjdropshipping', CjWebhookController::class)
    ->name('webhooks.cjdropshipping')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

// ── Redirect root to order form ──────────────────────────────────────────────
Route::get('/', fn () => redirect()->route('order.index'));


// ── Public Order Flow ─────────────────────────────────────────────────────────
Route::prefix('order')->name('order.')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');

    // Step 1: Fetch product (dispatches job)
    Route::post('/fetch-product', [OrderController::class, 'fetchProduct'])->name('fetch-product');
    Route::get('/fetch-product/{jobKey}', [OrderController::class, 'fetchProductResult'])->name('fetch-product.result');

    // Step 1: Server-side fee calculation
    Route::post('/calculate-fees', [OrderController::class, 'calculateFees'])->name('calculate-fees');

    // Step 3: Create Stripe Checkout Session
    Route::post('/create-session', [OrderController::class, 'createSession'])->name('create-session');

    // Card Scan & Custom Payment
    Route::post('/scan/initiate', [OrderController::class, 'initiateCardScan'])->name('scan.initiate');
    Route::get('/scan/status/{scanId}', [OrderController::class, 'getScanStatus'])->name('scan.status');
    Route::post('/charge', [OrderController::class, 'processPayment'])->name('charge');
    Route::post('/mobile-money', [OrderController::class, 'processMobileMoney'])->name('mobile-money');

    // Stripe redirect pages
    Route::get('/success', [OrderController::class, 'success'])->name('success');
    Route::get('/cancel', [OrderController::class, 'cancel'])->name('cancel');

    // Manual quote confirmation (oversized)
    Route::get('/manual-quote-confirmation', [OrderController::class, 'manualQuoteConfirmation'])->name('manual-quote-confirmation');

    // PDF Receipt
    Route::get('/{order}/receipt', [OrderController::class, 'downloadReceipt'])->name('receipt');

    // Additional payment page (reconciliation)
    Route::get('/additional-payment/{order}', function (App\Models\Order $order, \Illuminate\Http\Request $request) {
        $clientSecret = $request->query('secret');
        return view('order.additional-payment', compact('order', 'clientSecret'));
    })->name('additional-payment');
});

// ── Tracking Page (magic link) ────────────────────────────────────────────────
Route::get('/track-order', [TrackingController::class, 'trackPublic'])
    ->name('order.track-public');

Route::get('/track/{order}', [TrackingController::class, 'show'])
    ->name('order.track')
    ->middleware('signed');

Route::post('/track/resend', [TrackingController::class, 'resend'])->name('order.resend');
Route::get('/track/resend-success', [TrackingController::class, 'resendSuccess'])->name('order.resend-success');

// ── Stripe Webhook (exclude CSRF) ─────────────────────────────────────────────
// Note: Excluded from CSRF via bootstrap/app.php or by using withoutMiddleware below
Route::post('/stripe/webhook', [OrderController::class, 'webhook'])
    ->name('stripe.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
