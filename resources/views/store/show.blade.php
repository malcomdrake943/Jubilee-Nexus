@extends('layouts.app')

@section('title', $storeProduct->name)
@section('meta_description', Str::limit(strip_tags((string) $storeProduct->description), 150))

@section('content')
<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

@php
    $variants = $storeProduct->variants ?: [[
        'vid'          => $storeProduct->cj_vid,
        'sku'          => $storeProduct->sku,
        'name'         => 'Standard',
        'key'          => 'Default',
        'retail_price' => (float) $storeProduct->retail_price,
        'weight_grams' => (int) $storeProduct->weight_grams,
        'image'        => $storeProduct->display_image_url,
    ]];
    $images = collect($storeProduct->gallery_images ?? [])
        ->prepend($storeProduct->display_image_url)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $initialUnitPrice = (float) ($variants[0]['retail_price'] ?? $storeProduct->retail_price);
    $initialTax = $storeProduct->calculateTaxAmount($initialUnitPrice, 1);
@endphp

<div class="max-w-5xl mx-auto px-4"
    x-data="storeProductCheckout(
        {{ json_encode($storeProduct->id) }},
        {{ json_encode($variants) }},
        {{ json_encode($images) }},
        {{ json_encode($deliveryOptions->map(fn ($o) => ['logisticName' => $o->name, 'logisticPrice' => (float) ($storeProduct->custom_shipping_price ?? app(\App\Services\CjDropshippingService::class)->applyShippingRules((float) ($o->price ?? 0))), 'logisticAging' => $o->duration . ' Days'])->values()) }},
        {{ json_encode($initialTax) }}
    )"
    x-init="init()" x-cloak>

    <div class="mb-6">
        <a href="{{ route('store.index') }}" class="inline-flex items-center gap-2 text-white/70 hover:text-white text-sm font-medium transition-colors">
            ← Back to Store
        </a>
    </div>

    <div class="glass-light rounded-3xl shadow-2xl overflow-hidden p-6 md:p-10 animate-slide-up">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

            <!-- Product Gallery -->
            <div>
                <div class="aspect-square bg-white rounded-2xl border border-gray-100 shadow-sm flex items-center justify-center p-6 overflow-hidden mb-3">
                    <template x-if="activeImage">
                        <img :src="activeImage" alt="{{ $storeProduct->name }}" class="w-full h-full object-contain">
                    </template>
                    <template x-if="!activeImage">
                        <span class="text-6xl">📦</span>
                    </template>
                </div>

                @if(count($images) > 1)
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <template x-for="(img, idx) in images" :key="idx">
                            <button type="button" @click="activeImage = img"
                                :class="activeImage === img ? 'border-brand-600 ring-2 ring-brand-500/20' : 'border-gray-200 opacity-75 hover:opacity-100'"
                                class="w-16 h-16 rounded-xl bg-white border p-1 flex-shrink-0 overflow-hidden transition-all">
                                <img :src="img" class="w-full h-full object-contain">
                            </button>
                        </template>
                    </div>
                @endif

                @if($storeProduct->description)
                    <div class="mt-6 bg-white/70 rounded-2xl p-5 border border-gray-200/70">
                        <h3 class="font-display font-bold text-gray-900 text-sm mb-2">Product Details</h3>
                        <div class="text-xs text-gray-600 leading-relaxed prose prose-sm max-w-none">
                            {!! nl2br(e(strip_tags((string) $storeProduct->description))) !!}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Configuration & Checkout Form -->
            <div class="space-y-6">
                <div>
                    @if($storeProduct->category_name)
                        <span class="inline-block bg-brand-50 text-brand-700 text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full border border-brand-200 mb-2">
                            {{ $storeProduct->category_name }}
                        </span>
                    @endif
                    <h1 class="text-2xl md:text-3xl font-display font-extrabold text-gray-900 leading-tight mb-2">
                        {{ $storeProduct->name }}
                    </h1>
                    <p class="text-2xl font-extrabold text-brand-700">
                        $<span x-text="currentUnitPrice().toFixed(2)"></span>
                        <span class="text-xs font-medium text-gray-500 ml-1">USD / item</span>
                    </p>
                </div>

                <!-- Variant Picker -->
                <div x-show="variants.length > 1 || (variants[0]?.name && variants[0]?.name !== 'Standard')">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select Option / Variant</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="v in variants" :key="v.vid || v.name">
                            <button type="button" @click="selectVariant(v)"
                                :class="selectedVid === v.vid ? 'border-brand-600 bg-brand-50 text-brand-800 ring-2 ring-brand-500/20' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                class="text-left px-3.5 py-2.5 rounded-xl border text-xs font-semibold transition-all flex items-center justify-between gap-2">
                                <span class="truncate" x-text="v.name || v.key || 'Standard'"></span>
                                <span class="text-brand-700 font-bold">$<span x-text="parseFloat(v.retail_price).toFixed(2)"></span></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Quantity & Destination Country -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Quantity</label>
                        <input type="number" x-model.number="quantity" min="1" max="100" @change="fetchShipping()"
                            class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Destination Country <span class="text-red-500">*</span></label>
                        <input type="text" x-model="country" @blur="fetchShipping()" placeholder="United States"
                            class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                    </div>
                </div>

                <!-- Shipping Options -->
                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200/70">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-semibold text-gray-800">Shipping Method</label>
                        <button type="button" @click="fetchShipping()" class="text-xs font-semibold text-brand-600 hover:text-brand-800">
                            <span x-text="loadingShipping ? 'Calculating...' : '↻ Refresh Quote'"></span>
                        </button>
                    </div>

                    <select x-model="selectedLogisticName" @change="onShippingChanged()"
                        class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <template x-for="opt in shippingOptions" :key="opt.logisticName">
                            <option :value="opt.logisticName"
                                x-text="`${opt.logisticName} (${opt.logisticAging}) — $${parseFloat(opt.logisticPrice).toFixed(2)}`">
                            </option>
                        </template>
                    </select>
                </div>

                <!-- Order Breakdown -->
                <div class="fee-card rounded-2xl p-4 space-y-2">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Items (<span x-text="quantity"></span> × $<span x-text="currentUnitPrice().toFixed(2)"></span>)</span>
                        <span>$<span x-text="subtotal().toFixed(2)"></span></span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Shipping (<span x-text="selectedLogisticName || 'Standard'"></span>)</span>
                        <span>$<span x-text="parseFloat(selectedShippingCost || 0).toFixed(2)"></span></span>
                    </div>
                    <div x-show="parseFloat(taxAmount || 0) > 0" class="flex justify-between text-sm text-gray-600">
                        <span>Estimated Tax</span>
                        <span>$<span x-text="parseFloat(taxAmount || 0).toFixed(2)"></span></span>
                    </div>
                    <div class="border-t border-brand-200 pt-2 flex justify-between font-bold text-gray-900">
                        <span>Total Due</span>
                        <span class="text-brand-700 text-lg">$<span x-text="total().toFixed(2)"></span></span>
                    </div>
                </div>

                <!-- Customer & Shipping Details -->
                <div class="space-y-3 pt-2 border-t border-gray-200">
                    <h3 class="font-display font-bold text-gray-900 text-base">Shipping & Contact Details</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input type="text" x-model="customerName" placeholder="Full Name *"
                            class="input-field sm:col-span-2 w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <input type="email" x-model="customerEmail" placeholder="Email Address *"
                            class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <input type="tel" x-model="customerPhone" placeholder="Phone Number *"
                            class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <input type="text" x-model="addressLine1" placeholder="Address Line 1 *"
                            class="input-field sm:col-span-2 w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <input type="text" x-model="addressLine2" placeholder="Address Line 2 (optional)"
                            class="input-field sm:col-span-2 w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <input type="text" x-model="city" placeholder="City *"
                            class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <input type="text" x-model="state" placeholder="State / Province"
                            class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <input type="text" x-model="postalCode" placeholder="Postal Code *"
                            class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                        <input type="text" x-model="customerNotes" placeholder="Order Notes (optional)"
                            class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                    </div>
                </div>

                <!-- Payment Method Selector -->
                <div>
                    <label class="block font-bold text-gray-800 text-sm mb-2">Payment Method</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="selectPaymentMethod('card')"
                            :class="paymentMethod === 'card' ? 'border-brand-600 bg-brand-50 text-brand-700 ring-2 ring-brand-500/20' : 'border-gray-200 bg-white text-gray-700'"
                            class="p-3 rounded-xl border-2 text-center font-bold text-xs transition-all">
                            💳 Credit / Debit Card
                        </button>
                        <button type="button" @click="selectPaymentMethod('mobile_money')"
                            :class="paymentMethod === 'mobile_money' ? 'border-brand-600 bg-brand-50 text-brand-700 ring-2 ring-brand-500/20' : 'border-gray-200 bg-white text-gray-700'"
                            class="p-3 rounded-xl border-2 text-center font-bold text-xs transition-all">
                            📱 Mobile Money
                        </button>
                    </div>
                </div>

                <!-- Card Scan Panel -->
                <div x-show="paymentMethod === 'card'" class="bg-gray-50 rounded-2xl p-4 border border-gray-200/70 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-700">Card Verification Scan</span>
                        <button type="button" @click="initiateCardScan(true)"
                            class="text-xs font-semibold text-brand-600 hover:text-brand-800">
                            <span x-text="scanStatus === 'idle' ? 'Generate Scan QR →' : '↻ Regenerate QR'"></span>
                        </button>
                    </div>

                    <template x-if="scanStatus === 'initiating'">
                        <p class="text-xs text-gray-500 text-center py-4">Generating secure scan link...</p>
                    </template>

                    <template x-if="scanStatus === 'polling' && scanUrl">
                        <div class="text-center py-2">
                            <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' + encodeURIComponent(scanUrl)"
                                alt="Card Scan QR" class="w-36 h-36 mx-auto rounded-xl border border-gray-200 mb-2">
                            <p class="text-xs text-gray-500">Scan QR code with your phone to verify your card.</p>
                        </div>
                    </template>

                    <template x-if="scanStatus === 'completed'">
                        <div class="space-y-2">
                            <p class="text-xs font-bold text-green-700">✓ Card Verified — Enter CVV to complete purchase</p>
                            <input type="text" x-model="cardNumber" readonly class="input-field w-full px-3 py-2 rounded-lg text-xs bg-gray-100">
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" x-model="cardExpiryMonth" readonly placeholder="MM" class="input-field px-3 py-2 rounded-lg text-xs bg-gray-100">
                                <input type="text" x-model="cardExpiryYear" readonly placeholder="YY" class="input-field px-3 py-2 rounded-lg text-xs bg-gray-100">
                                <input type="password" x-model="cardCvc" maxlength="4" placeholder="CVV *" class="input-field px-3 py-2 rounded-lg text-xs">
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Mobile Money Notice -->
                <div x-show="paymentMethod === 'mobile_money'" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-900">
                    To pay with Mobile Money, contact support at <strong>{{ $supportPhone }}</strong>. You can submit your order now and our team will confirm fulfillment.
                </div>

                <div x-show="checkoutError" x-text="checkoutError" class="text-red-600 text-xs bg-red-50 border border-red-200 rounded-xl px-4 py-3"></div>

                <button type="button" @click="submitOrder()" :disabled="submitting"
                    class="btn-primary w-full text-white py-4 rounded-2xl font-bold text-base disabled:opacity-50">
                    <span x-text="submitting ? 'Processing Order...' : (paymentMethod === 'card' ? (scanStatus === 'completed' ? 'Pay $' + total().toFixed(2) + ' & Order' : 'Start Card Scan & Checkout') : 'Complete Mobile Money Order')"></span>
                </button>
            </div>
        </div>
    </div>

    @if($relatedProducts->isNotEmpty())
        <div class="mt-12">
            <h2 class="text-2xl font-display font-bold text-white mb-6">More from Jubilee Store</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach($relatedProducts as $rel)
                    <a href="{{ route('store.show', $rel) }}" class="glass-light rounded-2xl p-4 hover:shadow-xl transition-all flex flex-col justify-between">
                        <div class="aspect-square bg-white rounded-xl p-3 mb-3 flex items-center justify-center overflow-hidden">
                            @if($rel->display_image_url)
                                <img src="{{ $rel->display_image_url }}" alt="{{ $rel->name }}" class="w-full h-full object-contain">
                            @else
                                <span class="text-4xl">📦</span>
                            @endif
                        </div>
                        <p class="font-bold text-gray-900 text-xs line-clamp-2 mb-1">{{ $rel->name }}</p>
                        <p class="font-extrabold text-brand-700 text-sm">${{ number_format((float) $rel->retail_price, 2) }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
function storeProductCheckout(productId, variants, images, fallbackShipping, initialTaxAmount) {
    return {
        productId,
        variants,
        images,
        activeImage: images[0] || null,
        selectedVid: variants[0]?.vid || null,
        quantity: 1,
        country: 'United States',
        shippingOptions: fallbackShipping.length ? fallbackShipping : [{ logisticName: 'CJPacket Ordinary', logisticPrice: 0, logisticAging: '7-15 Days' }],
        selectedLogisticName: fallbackShipping[0]?.logisticName || 'CJPacket Ordinary',
        selectedShippingCost: fallbackShipping[0]?.logisticPrice || 0,
        taxAmount: parseFloat(initialTaxAmount || 0),
        loadingShipping: false,

        customerName: '',
        customerEmail: '',
        customerPhone: '',
        addressLine1: '',
        addressLine2: '',
        city: '',
        state: '',
        postalCode: '',
        customerNotes: '',

        paymentMethod: 'card',
        scanId: null,
        scanUrl: null,
        scanStatus: 'idle',
        scanPollInterval: null,
        cardNumber: '',
        cardExpiryMonth: '',
        cardExpiryYear: '',
        cardCvc: '',
        stripePublishableKey: '{{ config("cashier.key") }}',
        submitting: false,
        checkoutError: '',

        init() {
            this.fetchShipping();
        },

        currentVariant() {
            return this.variants.find(v => v.vid === this.selectedVid) || this.variants[0] || {};
        },

        currentUnitPrice() {
            return parseFloat(this.currentVariant()?.retail_price || 0);
        },

        subtotal() {
            return this.currentUnitPrice() * Math.max(1, parseInt(this.quantity || 1));
        },

        total() {
            return this.subtotal() + parseFloat(this.selectedShippingCost || 0) + parseFloat(this.taxAmount || 0);
        },

        selectVariant(variant) {
            this.selectedVid = variant.vid;
            if (variant.image) {
                this.activeImage = variant.image;
            }
            this.fetchShipping();
        },

        selectPaymentMethod(method) {
            this.paymentMethod = method;
            this.checkoutError = '';
        },

        async fetchShipping() {
            if (!this.country) return;
            this.loadingShipping = true;
            try {
                const res = await fetch('{{ route("store.shipping") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        store_product_id: this.productId,
                        vid: this.selectedVid,
                        quantity: Math.max(1, parseInt(this.quantity || 1)),
                        country: this.country,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    if (typeof data.tax_amount !== 'undefined') {
                        this.taxAmount = parseFloat(data.tax_amount || 0);
                    }
                    if (Array.isArray(data.shipping_options) && data.shipping_options.length) {
                        this.shippingOptions = data.shipping_options;
                        const existing = this.shippingOptions.find(o => o.logisticName === this.selectedLogisticName);
                        const picked = existing || this.shippingOptions[0];
                        this.selectedLogisticName = picked.logisticName;
                        this.selectedShippingCost = picked.logisticPrice;
                    }
                }
            } catch {}
            this.loadingShipping = false;
        },

        onShippingChanged() {
            const picked = this.shippingOptions.find(o => o.logisticName === this.selectedLogisticName);
            this.selectedShippingCost = picked ? picked.logisticPrice : 0;
        },

        validateCustomer() {
            if (!this.customerName || !this.customerEmail || !this.customerPhone || !this.addressLine1 || !this.city || !this.postalCode || !this.country) {
                this.checkoutError = 'Please fill in all required shipping and contact fields (*).';
                return false;
            }
            this.checkoutError = '';
            return true;
        },

        async initiateCardScan(force = false) {
            if (!this.validateCustomer()) return;
            this.scanStatus = 'initiating';
            if (this.scanPollInterval) clearInterval(this.scanPollInterval);

            try {
                const res = await fetch('{{ route("order.scan.initiate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        customer_name: this.customerName,
                        customer_email: this.customerEmail,
                        customer_phone: this.customerPhone,
                        force,
                    }),
                });
                const data = await res.json();
                if (data.success && data.scan_id) {
                    this.scanId = data.scan_id;
                    this.scanUrl = data.scan_url;
                    this.scanStatus = 'polling';
                    this.pollScanStatus();
                } else {
                    this.scanStatus = 'failed';
                    this.checkoutError = 'Failed to start card verification scan.';
                }
            } catch {
                this.scanStatus = 'failed';
                this.checkoutError = 'Unable to connect to card scan service.';
            }
        },

        pollScanStatus() {
            let attempts = 0;
            this.scanPollInterval = setInterval(async () => {
                attempts++;
                if (this.scanId && this.scanId.startsWith('mock_scan_')) {
                    clearInterval(this.scanPollInterval);
                    this.cardNumber = '4242424242424242';
                    this.cardExpiryMonth = '12';
                    this.cardExpiryYear = '28';
                    this.scanStatus = 'completed';
                    return;
                }

                try {
                    const encRes = await fetch('https://admin.cardnest.io/api/scan/getEncryptedData', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ scanId: this.scanId }),
                    });
                    if (encRes.ok) {
                        const encData = await encRes.json();
                        if (encData.message === 'Scanned data retrieved successfully.' && encData.data) {
                            const ciphertext = encData.data.encrypted_data || encData.data.encryptedData;
                            if (ciphertext) {
                                clearInterval(this.scanPollInterval);
                                const decrypted = this.decryptWithAES128(ciphertext, 'S5GRSOfPs9r9cYhj');
                                const rawCard = decrypted.cardNumber || decrypted.final_ocr?.card_number?.value;
                                const rawExpiry = decrypted.expiryDate || decrypted.final_ocr?.expiry_date?.value;
                                if (rawCard) {
                                    this.cardNumber = rawCard.toString().replace(/\s+/g, '');
                                    if (rawExpiry) {
                                        const exp = rawExpiry.toString().replace(/\s+/g, '');
                                        if (exp.includes('/')) {
                                            const [m, y] = exp.split('/');
                                            this.cardExpiryMonth = m.trim();
                                            this.cardExpiryYear = y.trim().length === 4 ? y.trim().substring(2) : y.trim();
                                        } else if (exp.length === 4) {
                                            this.cardExpiryMonth = exp.substring(0, 2);
                                            this.cardExpiryYear = exp.substring(2);
                                        }
                                    }
                                    this.scanStatus = 'completed';
                                }
                            }
                        }
                    }
                } catch {}

                if (attempts >= 150) {
                    clearInterval(this.scanPollInterval);
                    this.scanStatus = 'failed';
                }
            }, 2000);
        },

        decryptWithAES128(encryptedData, encryptionKey) {
            const rawData = CryptoJS.enc.Base64.parse(encryptedData);
            const iv = CryptoJS.lib.WordArray.create(rawData.words.slice(0, 4));
            const ciphertext = CryptoJS.lib.WordArray.create(rawData.words.slice(4));
            let keyBytes = CryptoJS.enc.Utf8.parse(encryptionKey);
            keyBytes = keyBytes.sigBytes < 16
                ? CryptoJS.enc.Utf8.parse(encryptionKey.padEnd(16, '\0').substring(0, 16))
                : CryptoJS.lib.WordArray.create(keyBytes.words.slice(0, 4));
            const decrypted = CryptoJS.AES.decrypt({ ciphertext }, keyBytes, {
                iv,
                mode: CryptoJS.mode.CBC,
                padding: CryptoJS.pad.Pkcs7,
            });
            let text = decrypted.toString(CryptoJS.enc.Utf8).replace(/\x00/g, '').replace(/[\x00-\x1F\x7F-\x9F]/g, '').trim();
            return JSON.parse(text);
        },

        buildPayload() {
            return {
                store_product_id: this.productId,
                vid: this.selectedVid,
                quantity: Math.max(1, parseInt(this.quantity || 1)),
                logistic_name: this.selectedLogisticName,
                shipping_cost: parseFloat(this.selectedShippingCost || 0),
                customer_name: this.customerName,
                customer_email: this.customerEmail,
                customer_phone: this.customerPhone,
                shipping_address: {
                    line1: this.addressLine1,
                    line2: this.addressLine2 || null,
                    city: this.city,
                    state: this.state || null,
                    postal_code: this.postalCode,
                    country: this.country,
                },
                customer_notes: this.customerNotes || null,
            };
        },

        async submitOrder() {
            if (!this.validateCustomer()) return;

            if (this.paymentMethod === 'mobile_money') {
                this.submitting = true;
                try {
                    const res = await fetch('{{ route("store.checkout.mobile-money") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(this.buildPayload()),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || data.error || 'Checkout failed.');
                    window.location.href = data.redirect;
                } catch (err) {
                    this.checkoutError = err.message;
                    this.submitting = false;
                }
                return;
            }

            if (this.scanStatus !== 'completed') {
                return this.initiateCardScan();
            }

            if (!this.cardCvc || this.cardCvc.trim().length < 3) {
                this.checkoutError = 'Please enter your 3 or 4-digit card CVV/CVC code.';
                return;
            }

            this.submitting = true;
            this.checkoutError = '';
            try {
                let paymentMethodId = 'pm_mock_123456';
                if (this.stripePublishableKey && !this.stripePublishableKey.includes('your_publishable_key_here')) {
                    const stripe = Stripe(this.stripePublishableKey);
                    const result = await stripe.createPaymentMethod({
                        type: 'card',
                        card: {
                            number: this.cardNumber,
                            exp_month: this.cardExpiryMonth,
                            exp_year: this.cardExpiryYear,
                            cvc: this.cardCvc,
                        },
                        billing_details: {
                            name: this.customerName,
                            email: this.customerEmail,
                            phone: this.customerPhone,
                        },
                    });
                    if (result.error) throw new Error(result.error.message);
                    paymentMethodId = result.paymentMethod.id;
                }

                const payload = this.buildPayload();
                payload.payment_method_id = paymentMethodId;

                const res = await fetch('{{ route("store.checkout.card") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || data.error || 'Payment failed.');
                window.location.href = data.redirect;
            } catch (err) {
                this.checkoutError = err.message;
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
