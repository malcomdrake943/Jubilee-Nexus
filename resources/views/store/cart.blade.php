@extends('layouts.app')

@section('title', 'Shopping Cart')
@section('meta_description', 'Review your Jubilee Direct Store shopping cart and complete checkout for all items together.')

@section('content')
<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<div class="max-w-6xl mx-auto px-4"
    x-data="storeCartCheckout(
        {{ json_encode($cartItems) }},
        {{ json_encode($subtotal) }},
        {{ json_encode($taxAmount) }},
        {{ json_encode($shippingOptions) }}
    )"
    x-init="init()" x-cloak>

    <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
        <div>
            <a href="{{ route('store.index') }}" class="inline-flex items-center gap-2 text-white/70 hover:text-white text-sm font-medium transition-colors mb-2">
                ← Continue Shopping
            </a>
            <h1 class="text-3xl md:text-4xl font-display font-extrabold text-white">
                Your Shopping <span class="gradient-text">Cart</span>
            </h1>
        </div>
        <button x-show="items.length > 0" type="button" @click="clearCart()" :disabled="busy"
            class="px-4 py-2 rounded-xl border border-white/20 text-white/80 hover:text-white hover:bg-white/10 text-xs font-semibold transition-colors">
            Clear Cart
        </button>
    </div>

    <!-- Empty Cart State -->
    <div x-show="items.length === 0" class="glass-light rounded-3xl p-12 text-center shadow-2xl animate-slide-up">
        <div class="w-20 h-20 bg-brand-50 rounded-2xl flex items-center justify-center mx-auto mb-5 text-4xl">🛒</div>
        <h2 class="text-2xl font-display font-bold text-gray-900 mb-2">Your cart is empty</h2>
        <p class="text-gray-500 text-sm max-w-md mx-auto mb-6">
            Browse our store catalog and click "Add to Cart" on any product you would like to purchase together.
        </p>
        <a href="{{ route('store.index') }}" class="btn-primary text-white px-7 py-3.5 rounded-2xl text-sm font-bold inline-flex items-center gap-2">
            Browse Store Products →
        </a>
    </div>

    <!-- Populated Cart + Checkout -->
    <div x-show="items.length > 0" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start animate-slide-up">

        <!-- Left Column: Cart Items -->
        <div class="lg:col-span-7 space-y-4">
            <template x-for="item in items" :key="item.key">
                <div class="glass-light rounded-2xl p-4 sm:p-5 shadow-xl flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                    <a :href="item.url" class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl bg-white border border-gray-100 p-2 flex-shrink-0 flex items-center justify-center overflow-hidden">
                        <template x-if="item.image">
                            <img :src="item.image" :alt="item.name" class="w-full h-full object-contain">
                        </template>
                        <template x-if="!item.image">
                            <span class="text-3xl">📦</span>
                        </template>
                    </a>

                    <div class="flex-1 min-w-0">
                        <a :href="item.url" class="font-display font-bold text-gray-900 text-sm sm:text-base hover:text-brand-600 line-clamp-2" x-text="item.name"></a>
                        <p x-show="item.variant_name && item.variant_name !== 'Standard'" class="text-xs text-gray-500 mt-0.5">
                            Variant: <span class="font-semibold text-gray-700" x-text="item.variant_name"></span>
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            Unit price: <span class="font-bold text-brand-700">$<span x-text="parseFloat(item.unit_price).toFixed(2)"></span></span>
                        </p>
                    </div>

                    <div class="flex sm:flex-col items-center sm:items-end justify-between w-full sm:w-auto gap-3">
                        <div class="inline-flex items-center border border-gray-300 rounded-xl bg-white overflow-hidden">
                            <button type="button" @click="updateQuantity(item.key, item.quantity - 1)" :disabled="busy"
                                class="px-3 py-1.5 text-gray-700 hover:bg-gray-100 font-bold text-sm">−</button>
                            <span class="px-3 py-1.5 text-xs font-bold text-gray-900 min-w-[2rem] text-center" x-text="item.quantity"></span>
                            <button type="button" @click="updateQuantity(item.key, item.quantity + 1)" :disabled="busy || item.quantity >= 100"
                                class="px-3 py-1.5 text-gray-700 hover:bg-gray-100 font-bold text-sm">+</button>
                        </div>

                        <div class="text-right">
                            <p class="text-base font-extrabold text-brand-700">$<span x-text="parseFloat(item.subtotal).toFixed(2)"></span></p>
                            <button type="button" @click="removeItem(item.key)" :disabled="busy"
                                class="text-[11px] font-semibold text-red-600 hover:text-red-800 mt-0.5">
                                Remove
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Right Column: Combined Shipping, Summary & Checkout -->
        <div class="lg:col-span-5 glass-light rounded-3xl p-6 shadow-2xl space-y-5">
            <h2 class="font-display font-bold text-gray-900 text-xl">Order Summary & Checkout</h2>

            <!-- Destination Country & Shipping -->
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Destination Country <span class="text-red-500">*</span></label>
                    <input type="text" x-model="country" @blur="fetchShipping()" placeholder="United States"
                        class="input-field w-full px-4 py-2.5 rounded-xl text-gray-800 text-sm">
                </div>

                <div class="bg-gray-50 rounded-2xl p-3.5 border border-gray-200/70">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-gray-800">Shipping Method</label>
                        <button type="button" @click="fetchShipping()" class="text-xs font-semibold text-brand-600 hover:text-brand-800">
                            <span x-text="loadingShipping ? 'Calculating...' : '↻ Refresh Quote'"></span>
                        </button>
                    </div>
                    <select x-model="selectedLogisticName" @change="onShippingChanged()"
                        class="input-field w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                        <template x-for="opt in shippingOptions" :key="opt.logisticName">
                            <option :value="opt.logisticName"
                                x-text="`${opt.logisticName} (${opt.logisticAging}) — $${parseFloat(opt.logisticPrice).toFixed(2)}`">
                            </option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- Totals Card -->
            <div class="fee-card rounded-2xl p-4 space-y-2">
                <div class="flex justify-between text-sm text-gray-600">
                    <span>Items Subtotal (<span x-text="totalUnits()"></span> items)</span>
                    <span>$<span x-text="parseFloat(subtotal || 0).toFixed(2)"></span></span>
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
            <div class="space-y-2.5 pt-2 border-t border-gray-200">
                <h3 class="font-display font-bold text-gray-900 text-sm">Shipping & Contact Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <input type="text" x-model="customerName" placeholder="Full Name *"
                        class="input-field sm:col-span-2 w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                    <input type="email" x-model="customerEmail" placeholder="Email Address *"
                        class="input-field w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                    <input type="tel" x-model="customerPhone" placeholder="Phone Number *"
                        class="input-field w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                    <input type="text" x-model="addressLine1" placeholder="Address Line 1 *"
                        class="input-field sm:col-span-2 w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                    <input type="text" x-model="addressLine2" placeholder="Address Line 2 (optional)"
                        class="input-field sm:col-span-2 w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                    <input type="text" x-model="city" placeholder="City *"
                        class="input-field w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                    <input type="text" x-model="state" placeholder="State / Province"
                        class="input-field w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                    <input type="text" x-model="postalCode" placeholder="Postal Code *"
                        class="input-field w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                    <input type="text" x-model="customerNotes" placeholder="Order Notes (optional)"
                        class="input-field w-full px-3.5 py-2.5 rounded-xl text-gray-800 text-sm">
                </div>
            </div>

            <!-- Payment Method -->
            <div>
                <label class="block font-bold text-gray-800 text-xs mb-2">Payment Method</label>
                <div class="grid grid-cols-2 gap-2.5">
                    <button type="button" @click="selectPaymentMethod('card')"
                        :class="paymentMethod === 'card' ? 'border-brand-600 bg-brand-50 text-brand-700 ring-2 ring-brand-500/20' : 'border-gray-200 bg-white text-gray-700'"
                        class="p-2.5 rounded-xl border-2 text-center font-bold text-xs transition-all">
                        💳 Credit / Debit Card
                    </button>
                    <button type="button" @click="selectPaymentMethod('mobile_money')"
                        :class="paymentMethod === 'mobile_money' ? 'border-brand-600 bg-brand-50 text-brand-700 ring-2 ring-brand-500/20' : 'border-gray-200 bg-white text-gray-700'"
                        class="p-2.5 rounded-xl border-2 text-center font-bold text-xs transition-all">
                        📱 Mobile Money
                    </button>
                </div>
            </div>

            <!-- Card Verification Scan -->
            <div x-show="paymentMethod === 'card'" class="bg-gray-50 rounded-2xl p-4 border border-gray-200/70 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-700">Card Verification Scan</span>
                    <button type="button" @click="initiateCardScan(true)"
                        class="text-xs font-semibold text-brand-600 hover:text-brand-800">
                        <span x-text="scanStatus === 'idle' ? 'Generate Scan QR →' : '↻ Regenerate QR'"></span>
                    </button>
                </div>

                <template x-if="scanStatus === 'initiating'">
                    <p class="text-xs text-gray-500 text-center py-3">Generating secure scan link...</p>
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
                To pay with Mobile Money, contact support at <strong>{{ $supportPhone }}</strong>. You can submit your cart order now and our team will confirm fulfillment.
            </div>

            <div x-show="checkoutError" x-text="checkoutError" class="text-red-600 text-xs bg-red-50 border border-red-200 rounded-xl px-4 py-3"></div>

            <button type="button" @click="submitOrder()" :disabled="submitting"
                class="btn-primary w-full text-white py-4 rounded-2xl font-bold text-sm disabled:opacity-50">
                <span x-text="submitting ? 'Processing Order...' : (paymentMethod === 'card' ? (scanStatus === 'completed' ? 'Pay $' + total().toFixed(2) + ' & Place Order' : 'Start Card Scan & Checkout') : 'Complete Mobile Money Order')"></span>
            </button>
        </div>
    </div>
</div>

<script>
function storeCartCheckout(initialItems, initialSubtotal, initialTaxAmount, initialShippingOptions) {
    const fallbackOpts = Array.isArray(initialShippingOptions) && initialShippingOptions.length
        ? initialShippingOptions
        : [{ logisticName: 'CJPacket Ordinary', logisticPrice: 0, logisticAging: '7-15 Days' }];

    return {
        items: initialItems || [],
        subtotal: parseFloat(initialSubtotal || 0),
        taxAmount: parseFloat(initialTaxAmount || 0),
        shippingOptions: fallbackOpts,
        selectedLogisticName: fallbackOpts[0]?.logisticName || 'CJPacket Ordinary',
        selectedShippingCost: fallbackOpts[0]?.logisticPrice || 0,
        country: 'United States',
        loadingShipping: false,
        busy: false,

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
            if (this.items.length > 0) {
                this.fetchShipping();
            }
        },

        totalUnits() {
            return this.items.reduce((sum, i) => sum + parseInt(i.quantity || 0, 10), 0);
        },

        total() {
            return parseFloat(this.subtotal || 0) + parseFloat(this.selectedShippingCost || 0) + parseFloat(this.taxAmount || 0);
        },

        selectPaymentMethod(method) {
            this.paymentMethod = method;
            this.checkoutError = '';
        },

        onShippingChanged() {
            const picked = this.shippingOptions.find(o => o.logisticName === this.selectedLogisticName);
            this.selectedShippingCost = picked ? picked.logisticPrice : 0;
        },

        applyCartPayload(data) {
            this.items = data.items || [];
            this.subtotal = parseFloat(data.subtotal || 0);
            this.taxAmount = parseFloat(data.tax_amount || 0);
            if (typeof window.updateNavCartCount === 'function') {
                window.updateNavCartCount(data.cart_count);
            }
        },

        async updateQuantity(key, newQty) {
            this.busy = true;
            this.checkoutError = '';
            try {
                const res = await fetch('{{ route("store.cart.update") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ key, quantity: Math.max(0, parseInt(newQty || 0, 10)) }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.applyCartPayload(data);
                    if (this.items.length > 0) {
                        await this.fetchShipping();
                    }
                }
            } catch {}
            this.busy = false;
        },

        async removeItem(key) {
            this.busy = true;
            this.checkoutError = '';
            try {
                const res = await fetch('{{ route("store.cart.remove") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ key }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.applyCartPayload(data);
                    if (this.items.length > 0) {
                        await this.fetchShipping();
                    }
                }
            } catch {}
            this.busy = false;
        },

        async clearCart() {
            this.busy = true;
            try {
                const res = await fetch('{{ route("store.cart.clear") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.applyCartPayload(data);
                }
            } catch {}
            this.busy = false;
        },

        async fetchShipping() {
            if (!this.country || this.items.length === 0) return;
            this.loadingShipping = true;
            try {
                const res = await fetch('{{ route("store.cart.shipping") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ country: this.country }),
                });
                const data = await res.json();
                if (res.ok) {
                    if (typeof data.subtotal !== 'undefined') this.subtotal = parseFloat(data.subtotal || 0);
                    if (typeof data.tax_amount !== 'undefined') this.taxAmount = parseFloat(data.tax_amount || 0);
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
                    const res = await fetch('{{ route("store.cart.checkout.mobile-money") }}', {
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

                const res = await fetch('{{ route("store.cart.checkout.card") }}', {
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
