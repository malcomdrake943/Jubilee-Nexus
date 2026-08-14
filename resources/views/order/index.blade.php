@extends('layouts.app')

@section('title', 'Place an Order')
@section('meta_description', 'Order any product from Amazon, eBay or any online store. We buy it and ship it to you internationally.')

@section('content')
    <!-- CryptoJS library for CardNest AES-128 decryption -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js" crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>

    <div class="max-w-2xl mx-auto px-4"
        x-data="orderWizard({{ json_encode($feeRules) }}, {{ json_encode($sizeFeeRules) }}, {{ json_encode($platforms) }})"
        x-init="init()" x-cloak>

        <!-- Hero Header -->
        <div class="text-center mb-10 animate-fade-in">
            <div
                class="inline-flex items-center gap-2 bg-white/10 rounded-full px-4 py-2 text-white/70 text-xs font-medium mb-6 border border-white/20">
                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                Secure Payments
            </div>
            <h1 class="text-4xl md:text-5xl font-display font-extrabold text-white leading-tight mb-4">
                Shop Anywhere.<br><span class="gradient-text">Ship Everywhere.</span>
            </h1>
            <p class="text-white/60 text-lg max-w-xl mx-auto">
                Paste any product URL from Amazon, eBay, or any online store. We buy it for you and ship it straight to your
                door.
            </p>
        </div>

        <!-- Progress Steps -->
        <div class="flex items-center justify-center gap-0 mb-8">
            <template x-for="(label, i) in ['Platform', 'Product', 'Your Details', 'Payment']" :key="i">
                <div class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div class="step-dot w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300"
                            :class="step > i + 1 ? 'bg-green-500 text-white' : step === i + 1 ? 'active text-white' : 'bg-white/20 text-white/50'">
                            <template x-if="step > i + 1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                            </template>
                            <template x-if="step <= i + 1">
                                <span x-text="i + 1"></span>
                            </template>
                        </div>
                        <span class="text-xs mt-1 font-medium transition-colors"
                            :class="step === i + 1 ? 'text-white' : 'text-white/40'" x-text="label"></span>
                    </div>
                    <template x-if="i < 3">
                        <div class="w-16 md:w-24 h-px mx-2 mb-4 transition-colors duration-300"
                            :class="step > i + 1 ? 'bg-green-500' : 'bg-white/20'"></div>
                    </template>
                </div>
            </template>
        </div>

        <!-- Form Card -->
        <div class="glass-light rounded-3xl shadow-2xl overflow-hidden animate-slide-up">

            <!-- ══ STEP 1: Platform Selection ════════════════════════════════════════ -->
            <div x-show="step === 1" x-transition:enter="animate-slide-up">
                <div class="p-8">
                    <h2 class="text-2xl font-display font-bold text-gray-900 mb-1 text-center">Where would you like to shop?
                    </h2>
                    <p class="text-gray-500 text-sm mb-6 text-center">Select one of our partner platforms below to buy your
                        products.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                        <template x-for="p in platforms" :key="p.id">
                            <div class="relative flex flex-col justify-between p-6 rounded-2xl border transition-all duration-300 cursor-pointer overflow-hidden group select-none"
                                :class="selectedPlatformId === p.id ? 'bg-brand-50 border-brand-500 ring-2 ring-brand-500/20' : 'bg-white/50 border-gray-200/60 hover:bg-white hover:border-brand-300 hover:shadow-lg'"
                                @click="selectPlatform(p)">

                                <!-- Selection badge -->
                                <div class="absolute top-3 right-3 w-5 h-5 rounded-full flex items-center justify-center border text-xs"
                                    :class="selectedPlatformId === p.id ? 'bg-brand-600 border-brand-600 text-white' : 'border-gray-300 text-transparent'">
                                    ✓
                                </div>

                                <!-- Brand Logo & Name -->
                                <div class="flex flex-col items-center text-center mt-2 flex-grow">
                                    <div
                                        class="w-20 h-20 rounded-full bg-white flex items-center justify-center p-2 shadow-sm border border-gray-100 group-hover:scale-105 transition-transform duration-300 mb-3">
                                        <img :src="'/storage/' + p.logo" :alt="p.name"
                                            class="w-full h-full object-contain rounded-full">
                                    </div>
                                    <span class="font-bold text-gray-900 text-base" x-text="p.name"></span>
                                </div>

                                <!-- Visit Store Button -->
                                <div class="mt-4 pt-3 border-t border-gray-100/60 w-full text-center">
                                    <a :href="p.url" target="_blank" @click.stop
                                        class="inline-block text-xs font-semibold text-brand-600 hover:text-brand-700 bg-brand-50/50 hover:bg-brand-50 px-3 py-1.5 rounded-lg border border-brand-200/50 transition-colors w-full">
                                        Visit Store ↗
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Validation Error -->
                    <div x-show="!selectedPlatformId && step1Error" x-text="step1Error"
                        class="text-red-600 text-sm text-center mb-4 bg-red-50 rounded-xl px-4 py-3"></div>

                    <!-- Continue Button -->
                    <button
                        @click="if(selectedPlatformId) { step = 2; } else { step1Error = 'Please select a platform to proceed.'; }"
                        :disabled="!selectedPlatformId"
                        class="btn-primary w-full text-white py-4 rounded-2xl font-bold text-base flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        Continue to Product Details
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ══ STEP 2: Product & Pricing ══════════════════════════════════════════ -->
            <div x-show="step === 2" x-transition:enter="animate-slide-up">
                <div class="p-8">
                    <!-- Selected Platform Banner -->
                    <div x-show="selectedPlatformId"
                        class="flex items-center justify-between bg-brand-50/70 border border-brand-100 rounded-2xl px-4 py-3 mb-6 animate-fade-in">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-full bg-white flex items-center justify-center p-1.5 border border-gray-100 shadow-sm">
                                <template x-if="getSelectedPlatform()?.logo">
                                    <img :src="'/storage/' + getSelectedPlatform()?.logo" :alt="getSelectedPlatform()?.name"
                                        class="w-full h-full object-contain rounded-full">
                                </template>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 font-medium">Shopping on</p>
                                <p class="text-sm font-bold text-gray-900" x-text="getSelectedPlatform()?.name"></p>
                            </div>
                        </div>
                        <button @click="step = 1"
                            class="text-xs font-semibold text-brand-600 hover:text-brand-700 bg-white hover:bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200 transition-colors shadow-sm">
                            ← Change Store
                        </button>
                    </div>

                    <h2 class="text-2xl font-display font-bold text-gray-900 mb-1">What would you like to order?</h2>
                    <p class="text-gray-500 text-sm mb-6">Paste the product URL and we'll fetch its details automatically.
                    </p>

                    <!-- URL Input -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product URL</label>
                        <div class="flex gap-2">
                            <input type="url" x-model="productUrl"
                                placeholder="https://amazon.com/dp/... or https://ebay.com/itm/..."
                                class="input-field flex-1 px-4 py-3.5 rounded-xl text-gray-800 text-sm"
                                @input.debounce.500ms="productFetchResult = null">
                            <button @click="fetchProduct()" :disabled="!productUrl || fetchingProduct"
                                class="btn-primary text-white px-5 py-3.5 rounded-xl text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 whitespace-nowrap">
                                <svg x-show="fetchingProduct" class="spinner w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                                </svg>
                                <span x-text="fetchingProduct ? 'Fetching...' : 'Fetch Product'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Product Card (success) -->
                    <div x-show="productFetchResult && productFetchResult.status === 'done' && productFetchResult.data"
                        x-transition class="fee-card rounded-2xl p-4 mb-5">
                        <div class="flex gap-4">
                            <img x-show="productFetchResult?.data?.image_url" :src="productFetchResult?.data?.image_url"
                                class="w-20 h-20 object-cover rounded-xl border border-white/50 flex-shrink-0"
                                x-on:error="$el.style.display='none'">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="platform-badge px-2 py-0.5 rounded-full text-white"
                                        :class="productFetchResult?.data?.platform === 'amazon' ? 'bg-orange-500' : productFetchResult?.data?.platform === 'ebay' ? 'bg-red-500' : 'bg-brand-500'"
                                        x-text="productFetchResult?.data?.platform?.toUpperCase() ?? 'OTHER'"></span>
                                </div>
                                <p class="font-semibold text-gray-900 text-sm line-clamp-2"
                                    x-text="productFetchResult?.data?.name"></p>
                                <p class="text-gray-500 text-xs mt-1 line-clamp-2"
                                    x-text="productFetchResult?.data?.description"></p>
                                <p x-show="productFetchResult?.data?.price" class="text-brand-600 font-bold text-sm mt-1">
                                    Detected price: $<span x-text="productFetchResult?.data?.price?.toFixed(2)"></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Fetch Failed / Manual Entry Notice -->
                    <div x-show="productFetchResult && (productFetchResult.status === 'failed' || !productFetchResult.data)"
                        x-transition class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5">
                        <div class="flex gap-3">
                            <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="text-amber-800 text-sm font-medium">We couldn't fetch product details
                                    automatically</p>
                                <p class="text-amber-700 text-xs mt-0.5">This often happens with Amazon/eBay bot protection.
                                    Please fill in the product details below manually — your order will not be affected.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Product Name (manual or pre-filled) -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Product Name <span
                                class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" x-model="productName" placeholder="e.g. Sony WH-1000XM5 Headphones"
                            class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                    </div>

                    <!-- Price, Qty, Size Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="md:col-span-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Price (USD) <span
                                    class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 font-medium">$</span>
                                <input type="number" x-model.number="estimatedPrice" min="0.01" step="0.01"
                                    placeholder="0.00"
                                    class="input-field w-full pl-7 pr-4 py-3.5 rounded-xl text-gray-800 text-sm"
                                    @input="recalculateFees()">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity <span
                                    class="text-red-500">*</span></label>
                            <input type="number" x-model.number="quantity" min="1" max="100"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm"
                                @input="recalculateFees()">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Package Size <span
                                    class="text-red-500">*</span></label>
                            <select x-model="sizeTier"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm"
                                @change="recalculateFees()">
                                <option value="">Select size</option>
                                <option value="small">📦 Small</option>
                                <option value="medium">📦 Medium (+$5)</option>
                                <option value="large">📦 Large (+$12)</option>
                                <option value="oversized">🏗️ Oversized (quote)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Live Fee Breakdown -->
                    <div x-show="sizeTier && estimatedPrice > 0" x-transition class="fee-card rounded-2xl p-5 mb-6">
                        <!-- Oversized: manual quote notice -->
                        <div x-show="sizeFeeRules[sizeTier]?.requires_manual_quote" class="text-center py-2">
                            <p class="text-brand-700 font-semibold text-sm">🏗️ Oversized items require a manual quote</p>
                            <p class="text-gray-500 text-xs mt-1">We'll review your order and email you a payment link
                                within 1–2 business days. No payment now.</p>
                        </div>

                        <!-- Normal fee breakdown -->
                        <div x-show="!sizeFeeRules[sizeTier]?.requires_manual_quote" class="space-y-2">
                            <h3 class="font-semibold text-gray-800 text-sm mb-3">Order Total Estimate</h3>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Product price <span x-show="quantity > 1" class="text-gray-400">(×<span
                                            x-text="quantity"></span>)</span></span>
                                <span>$<span x-text="(estimatedPrice * quantity).toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Service fee (<span x-text="getFeePercent()"></span>%)</span>
                                <span>$<span x-text="computedTierFee.toFixed(2)"></span></span>
                            </div>
                            <div x-show="computedSizeFee > 0" class="flex justify-between text-sm text-gray-600">
                                <span>Handling fee (<span x-text="sizeTier"></span>)</span>
                                <span>$<span x-text="computedSizeFee.toFixed(2)"></span></span>
                            </div>
                            <div class="border-t border-brand-200 pt-2 mt-2 flex justify-between">
                                <span class="font-bold text-gray-900">Total</span>
                                <span class="font-bold text-brand-700 text-lg">$<span
                                        x-text="computedTotal.toFixed(2)"></span></span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">* Final price subject to reconciliation after actual
                                purchase</p>
                        </div>
                    </div>

                    <!-- Validation Error -->
                    <div x-show="step1Error" x-text="step1Error"
                        class="text-red-600 text-sm text-center mb-4 bg-red-50 rounded-xl px-4 py-3"></div>

                    <!-- Next Button -->
                    <div class="flex gap-3">
                        <button @click="step = 1"
                            class="flex-1 py-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-colors">
                            ← Back
                        </button>
                        <button @click="validateStep1()"
                            class="flex-2 btn-primary text-white px-8 py-4 rounded-2xl font-bold flex items-center justify-center gap-2">
                            Continue to Your Details
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══ STEP 3: Customer & Shipping Details ════════════════════════════════ -->
            <div x-show="step === 3" x-transition:enter="animate-slide-up">
                <div class="p-8">
                    <h2 class="text-2xl font-display font-bold text-gray-900 mb-1">Where should we ship?</h2>
                    <p class="text-gray-500 text-sm mb-6">We'll deliver your order to this address and keep you updated by
                        email.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name <span
                                    class="text-red-500">*</span></label>
                            <input type="text" x-model="customerName" placeholder="John Doe"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address <span
                                    class="text-red-500">*</span></label>
                            <input type="email" x-model="customerEmail" placeholder="john@example.com"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number <span
                                    class="text-red-500">*</span></label>
                            <input type="tel" x-model="customerPhone" placeholder="+1 (555) 000-0000"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Address Line 1 <span
                                    class="text-red-500">*</span></label>
                            <input type="text" x-model="addressLine1" placeholder="123 Main Street"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Address Line 2 <span
                                    class="text-gray-400 font-normal">(optional)</span></label>
                            <input type="text" x-model="addressLine2" placeholder="Apartment, suite, unit..."
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">City <span
                                    class="text-red-500">*</span></label>
                            <input type="text" x-model="city" placeholder="New York"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">State / Province</label>
                            <input type="text" x-model="state" placeholder="NY"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Postal Code <span
                                    class="text-red-500">*</span></label>
                            <input type="text" x-model="postalCode" placeholder="10001"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Country <span
                                    class="text-red-500">*</span></label>
                            <input type="text" x-model="country" placeholder="United States"
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Order Notes <span
                                    class="text-gray-400 font-normal">(optional)</span></label>
                            <textarea x-model="customerNotes" rows="3"
                                placeholder="Specific color, size, variant requests..."
                                class="input-field w-full px-4 py-3.5 rounded-xl text-gray-800 text-sm resize-none"></textarea>
                        </div>
                    </div>

                    <div x-show="step2Error" x-text="step2Error"
                        class="text-red-600 text-sm text-center mt-4 bg-red-50 rounded-xl px-4 py-3"></div>

                    <div class="flex gap-3 mt-6">
                        <button @click="step = 2"
                            class="flex-1 py-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-colors">
                            ← Back
                        </button>
                        <button @click="validateStep2()"
                            class="flex-2 btn-primary text-white px-8 py-4 rounded-2xl font-bold flex items-center gap-2">
                            Continue to Payment
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══ STEP 4: Payment / Review ═══════════════════════════════════════════ -->
            <div x-show="step === 4" x-transition:enter="animate-slide-up">
                <div class="p-8">
                    <h2 class="text-2xl font-display font-bold text-gray-900 mb-1">Review & Pay</h2>
                    <p class="text-gray-500 text-sm mb-6">Please review your order details before completing payment.</p>

                    <!-- Order Summary Card -->
                    <div class="bg-gray-50 rounded-2xl p-5 mb-6 space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 bg-brand-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800 text-sm"
                                    x-text="productName || 'Product from ' + productUrl"></p>
                                <p class="text-gray-500 text-xs mt-0.5">Qty: <span x-text="quantity"></span> · <span
                                        x-text="sizeTier" class="capitalize"></span> package</p>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 pt-3 space-y-1.5">
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Product subtotal</span>
                                <span>$<span x-text="(estimatedPrice * quantity).toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Service fee</span>
                                <span>$<span x-text="computedTierFee.toFixed(2)"></span></span>
                            </div>
                            <div x-show="computedSizeFee > 0" class="flex justify-between text-sm text-gray-600">
                                <span>Handling fee</span>
                                <span>$<span x-text="computedSizeFee.toFixed(2)"></span></span>
                            </div>
                            <div class="flex justify-between font-bold text-gray-900 pt-1 border-t border-gray-200">
                                <span>Total Due Today</span>
                                <span class="text-brand-700 text-lg">$<span x-text="computedTotal.toFixed(2)"></span></span>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 pt-3">
                            <p class="text-xs text-gray-500"><strong>Delivering to:</strong> <span
                                    x-text="customerName"></span>, <span x-text="city"></span>, <span
                                    x-text="country"></span></p>
                            <p class="text-xs text-gray-500 mt-0.5"><strong>Confirmation to:</strong> <span
                                    x-text="customerEmail"></span></p>
                        </div>
                    </div>

                    <!-- Oversized: no payment -->
                    <div x-show="sizeFeeRules[sizeTier]?.requires_manual_quote"
                        class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6">
                        <div class="flex gap-3">
                            <svg class="w-6 h-6 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="font-semibold text-amber-900 text-sm">No payment needed yet</p>
                                <p class="text-amber-700 text-xs mt-0.5">Your order will be placed under review and our team
                                    will email you a payment link within 1–2 business days.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Selection (Card vs Mobile Money) -->
                    <div x-show="!sizeFeeRules[sizeTier]?.requires_manual_quote" class="mb-6">
                        <label class="block font-bold text-gray-800 text-sm mb-3">Select Payment Method</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" @click="selectPaymentMethod('card')"
                                :class="paymentMethod === 'card' ? 'border-brand-600 bg-brand-50/50 text-brand-700 ring-2 ring-brand-500/20 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                class="flex flex-col items-center justify-center p-4 rounded-2xl border-2 transition-all text-center">
                                <span class="text-2xl mb-1">💳</span>
                                <span class="font-bold text-sm">Credit / Debit Card</span>
                                <span class="text-xs text-gray-400 mt-0.5">Pay securely via card scan</span>
                            </button>
                            <button type="button" @click="selectPaymentMethod('mobile_money')"
                                :class="paymentMethod === 'mobile_money' ? 'border-brand-600 bg-brand-50/50 text-brand-700 ring-2 ring-brand-500/20 shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                class="flex flex-col items-center justify-center p-4 rounded-2xl border-2 transition-all text-center">
                                <span class="text-2xl mb-1">📱</span>
                                <span class="font-bold text-sm">Mobile Money</span>
                                <span class="text-xs text-gray-400 mt-0.5">Contact customer support</span>
                            </button>
                        </div>
                    </div>

                    <!-- Mobile Money Notice -->
                    <div x-show="!sizeFeeRules[sizeTier]?.requires_manual_quote && paymentMethod === 'mobile_money'"
                        class="bg-amber-50/90 border border-amber-200 rounded-2xl p-5 mb-6 animate-fade-in">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 bg-amber-100/80 rounded-xl flex items-center justify-center flex-shrink-0 text-amber-700 font-bold text-xl">
                                📱
                            </div>
                            <div>
                                <h4 class="font-bold text-amber-950 text-sm mb-1">Mobile Money Instructions</h4>
                                <p class="text-amber-900 text-sm leading-relaxed">
                                    To pay with mobile money, contact our customer support team on this number <strong class="font-extrabold text-amber-950 underline decoration-amber-400 decoration-2">{{ $supportPhone }}</strong>, and you will be provided with the mobile money details. In the meantime, you can complete your order for processing.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Scan Card & Card Info Section -->
                    <div x-show="!sizeFeeRules[sizeTier]?.requires_manual_quote && paymentMethod === 'card'"
                        class="bg-gray-50 rounded-2xl p-6 mb-6 border border-gray-200/60">
                        <h3 class="font-bold text-gray-800 text-sm mb-2 flex items-center gap-1.5">
                            💳 Card Payment Details
                        </h3>

                        <p
                            class="text-xs text-gray-500 mb-4 font-medium bg-blue-50 border border-blue-100 rounded-xl p-3 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            We need to Scan your card for security purposes.
                        </p>

                        <div class="flex flex-col items-center justify-center py-4">
                            <!-- QR Code Container -->
                            <div
                                class="w-full max-w-sm flex flex-col items-center justify-center border border-dashed border-gray-300 rounded-2xl p-6 bg-white text-center min-h-[220px] shadow-sm">
                                <template x-if="scanStatus === 'polling' && scanUrl">
                                    <div class="animate-fade-in">
                                        <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(scanUrl)"
                                            alt="Card Scan QR Code"
                                            class="w-40 h-40 mb-4 rounded-xl border border-gray-100 shadow-md mx-auto" />
                                        <p class="text-sm font-bold text-gray-800">Scan QR Code</p>
                                        <p class="text-xs text-gray-400 mt-1">Point your mobile camera at this QR code to
                                            scan your card securely</p>
                                    </div>
                                </template>
                                <template x-if="scanStatus === 'initiating'">
                                    <div class="py-12">
                                        <svg class="animate-spin h-10 w-10 text-brand-600 mx-auto" fill="none"
                                            viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                                        </svg>
                                        <p class="text-sm text-gray-500 mt-4 font-medium">Generating Scan Link...</p>
                                    </div>
                                </template>
                                <template x-if="scanStatus === 'completed'">
                                    <div class="w-full text-left animate-fade-in space-y-4">
                                        <div class="flex items-center gap-2 pb-2 border-b border-gray-100">
                                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-green-600 font-bold">✓</div>
                                            <div>
                                                <p class="text-sm font-bold text-gray-800">Scan Complete!</p>
                                                <p class="text-xs text-gray-400">Please enter your card's CVV to pay securely.</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Card Details Form -->
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-500 mb-1">Card Number</label>
                                                <input type="text" x-model="cardNumber" readonly
                                                    class="input-field w-full px-4 py-2.5 rounded-xl text-gray-800 text-sm bg-gray-50 cursor-not-allowed select-none border-gray-200">
                                            </div>
                                            <div class="grid grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Exp Month</label>
                                                    <input type="text" x-model="cardExpiryMonth" readonly
                                                        class="input-field w-full px-4 py-2.5 rounded-xl text-gray-800 text-sm bg-gray-50 cursor-not-allowed select-none border-gray-200">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Exp Year</label>
                                                    <input type="text" x-model="cardExpiryYear" readonly
                                                        class="input-field w-full px-4 py-2.5 rounded-xl text-gray-800 text-sm bg-gray-50 cursor-not-allowed select-none border-gray-200">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-700 mb-1">CVV / CVC <span class="text-red-500">*</span></label>
                                                    <input type="text" x-model="cardCvc" maxlength="4" required placeholder="123"
                                                        class="input-field w-full px-4 py-2.5 rounded-xl text-gray-800 text-sm border-brand-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="scanStatus === 'failed'">
                                    <div class="py-12 animate-fade-in">
                                        <div
                                            class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3 text-red-600 font-extrabold text-2xl shadow-sm">
                                            ✗</div>
                                        <p class="text-base font-bold text-red-800">Scan Failed</p>
                                        <p class="text-xs text-gray-400 mt-1 mb-4">Could not retrieve card information.</p>
                                        <button @click="initiateCardScan(true)"
                                            class="inline-flex items-center gap-1 bg-brand-50 hover:bg-brand-100 text-brand-700 font-semibold px-4 py-2 rounded-xl text-xs transition-colors border border-brand-200">
                                            Try again
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Security badges -->
                    <div class="flex items-center justify-center gap-4 mb-6 text-xs text-gray-400">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            SSL Encrypted
                        </div>
                        <div class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Error -->
                    <div x-show="paymentError" x-text="paymentError"
                        class="text-red-600 text-sm text-center mb-4 bg-red-50 rounded-xl px-4 py-3"></div>

                    <div class="flex gap-3">
                        <button @click="step = 3"
                            class="flex-1 py-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-colors">
                            ← Back
                        </button>
                        <button @click="submitOrder()" :disabled="submitting"
                            class="flex-2 btn-primary text-white px-8 py-4 rounded-2xl font-bold flex items-center gap-2 disabled:opacity-60">
                            <svg x-show="submitting" class="spinner w-5 h-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                            </svg>
                            <template x-if="sizeFeeRules[sizeTier]?.requires_manual_quote">
                                <span x-text="submitting ? 'Submitting...' : 'Submit for Quote'"></span>
                            </template>
                            <template x-if="!sizeFeeRules[sizeTier]?.requires_manual_quote">
                                <span
                                    x-text="submitting ? 'Processing...' : (paymentMethod === 'mobile_money' ? 'Complete Order for Processing' : 'Pay $' + computedTotal.toFixed(2) + ' Securely')"></span>
                            </template>
                        </button>
                    </div>

                    <p class="text-center text-xs text-gray-400 mt-4">
                        By placing this order you agree to our terms of service. We'll email your order tracking link.
                    </p>
                </div>
            </div>
        </div>

        <!-- Trust Badges -->
        <div class="grid grid-cols-3 gap-4 mt-8 px-2 animate-fade-in">
            <div class="glass rounded-2xl p-5 text-center">
                <div class="text-2xl mb-2">🛒</div>
                <p class="text-white font-semibold text-sm">Any Store</p>
                <p class="text-white/50 text-xs mt-0.5">Amazon, Walmart, and more</p>
            </div>
            <div class="glass rounded-2xl p-5 text-center">
                <div class="text-2xl mb-2">🌍</div>
                <p class="text-white font-semibold text-sm">Global Shipping</p>
                <p class="text-white/50 text-xs mt-0.5">We ship to 150+ countries</p>
            </div>
            <div class="glass rounded-2xl p-5 text-center">
                <div class="text-2xl mb-2">🔒</div>
                <p class="text-white font-semibold text-sm">Secure Payment</p>
                <p class="text-white/50 text-xs mt-0.5">Multiple Secure Payment Options</p>
            </div>
        </div>

        <!-- ══ Mobile Money Modal Popup ═══════════════════════════════════════════ -->
        <div x-show="showMobileMoneyModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
            x-cloak>
            <div @click.away="showMobileMoneyModal = false"
                class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-gray-100 relative animate-fade-in">
                <!-- Close Button -->
                <button @click="showMobileMoneyModal = false" type="button"
                    class="absolute top-5 right-5 text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full hover:bg-gray-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Icon & Header -->
                <div class="flex items-center gap-4 mb-5">
                    <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center text-amber-600 text-2xl font-bold flex-shrink-0 shadow-inner">
                        📱
                    </div>
                    <div>
                        <h3 class="text-xl font-display font-bold text-gray-900">Mobile Money</h3>
                        <p class="text-xs text-gray-500 font-medium">Payment Instructions</p>
                    </div>
                </div>

                <!-- Message Box -->
                <div class="bg-amber-50/90 border border-amber-200/80 rounded-2xl p-5 mb-6 text-left">
                    <p class="text-sm text-amber-950 leading-relaxed">
                        To Pay with mobile money, contact our customer support team on this number <a href="tel:{{ $supportPhone }}" class="font-extrabold text-amber-950 underline decoration-amber-400 decoration-2 hover:text-brand-600 transition-colors">{{ $supportPhone }}</a>, and you will be provided with the mobile money details. In meantime, you can complete your order for processing
                    </p>
                </div>

                <!-- Action Button -->
                <button @click="showMobileMoneyModal = false" type="button"
                    class="w-full btn-primary text-white py-3.5 px-6 rounded-2xl font-bold text-sm shadow-lg shadow-brand-500/20 hover:shadow-brand-500/30 transition-all">
                    Understood, Proceed to Order
                </button>
            </div>
        </div>
    </div>

    <script>
        function orderWizard(feeRules, sizeFeeRules, platforms) {
            return {
                // Navigation
                step: 1,

                platforms,
                selectedPlatformId: null,
                authToken: null,

                selectPlatform(platform) {
                    this.selectedPlatformId = platform.id;
                    this.step = 2;
                },

                getSelectedPlatform() {
                    return this.platforms.find(p => p.id === this.selectedPlatformId);
                },

                // Step 2: Product (originally Step 1)
                productUrl: '',
                productName: '',
                productImageUrl: '',
                estimatedPrice: '',
                quantity: 1,
                sizeTier: '',
                fetchingProduct: false,
                productFetchResult: null,
                fetchJobKey: null,
                fetchPollInterval: null,
                step1Error: '',

                // Step 2: Customer
                customerName: '',
                customerEmail: '',
                customerPhone: '',
                addressLine1: '',
                addressLine2: '',
                city: '',
                state: '',
                postalCode: '',
                country: '',
                customerNotes: '',
                step2Error: '',

                // Step 3: Payment & Scan
                paymentMethod: 'card', // 'card', 'mobile_money'
                showMobileMoneyModal: false,
                paymentError: '',

                selectPaymentMethod(method) {
                    this.paymentMethod = method;
                    if (method === 'mobile_money') {
                        this.showMobileMoneyModal = true;
                    }
                },
                submitting: false,
                scanId: null,
                scanUrl: null,
                scanStatus: 'idle', // 'idle', 'initiating', 'polling', 'completed', 'failed'
                cardNumber: '',
                cardExpiryMonth: '',
                cardExpiryYear: '',
                cardCvc: '',
                scanPollInterval: null,
                stripePublishableKey: '{{ config('cashier.key') }}',

                // Fee data
                feeRules,
                sizeFeeRules,
                computedTierFee: 0,
                computedSizeFee: 0,
                computedTotal: 0,

                init() {
                    // If URL param has pre-filled URL
                    const urlParam = new URLSearchParams(window.location.search).get('url');
                    if (urlParam) {
                        this.productUrl = urlParam;
                        try {
                            // Try to detect platform automatically
                            const host = new URL(urlParam).hostname.toLowerCase();
                            const matchedPlatform = this.platforms.find(p => {
                                try {
                                    const pHost = new URL(p.url).hostname.toLowerCase();
                                    return host.includes(pHost) || pHost.includes(host);
                                } catch {
                                    return false;
                                }
                            });
                            if (matchedPlatform) {
                                this.selectedPlatformId = matchedPlatform.id;
                                this.step = 2; // proceed to Step 2 directly
                            } else {
                                // Fallback to Amazon
                                const amazonPlatform = this.platforms.find(p => p.name.toLowerCase() === 'amazon');
                                if (amazonPlatform) {
                                    this.selectedPlatformId = amazonPlatform.id;
                                    this.step = 2;
                                }
                            }
                        } catch {
                            if (this.platforms.length > 0) {
                                this.selectedPlatformId = this.platforms[0].id;
                                this.step = 2;
                            }
                        }
                        this.fetchProduct();
                    }
                },

                // ── Card Scan Lifecycle ──────────────────────────────────────────────────

                async initiateCardScan(force = false) {
                    this.scanStatus = 'initiating';
                    this.scanId = null;
                    this.scanUrl = null;
                    this.authToken = null;
                    if (this.scanPollInterval) {
                        clearInterval(this.scanPollInterval);
                    }

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
                                force: force
                            })
                        });

                        const data = await res.json();
                        if (data.success && data.scan_id) {
                            this.scanId = data.scan_id;
                            this.scanUrl = data.scan_url;
                            this.authToken = data.token;
                            this.scanStatus = 'polling';
                            this.pollScanStatus();
                        } else {
                            this.scanStatus = 'failed';
                        }
                    } catch {
                        this.scanStatus = 'failed';
                    }
                },

                pollScanStatus() {
                    let attempts = 0;
                    const maxAttempts = 150; // 5 minutes max (150 * 2s)

                    this.scanPollInterval = setInterval(async () => {
                        attempts++;

                        // Handle mock scan immediately to prevent external API calls during testing
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
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ scanId: this.scanId }),
                            });

                            if (encRes.ok) {
                                const encData = await encRes.json();

                                if (encData.message === "Scanned data retrieved successfully." && encData.data) {
                                    const ciphertext = encData.data.encrypted_data || encData.data.encryptedData;

                                    if (ciphertext) {
                                        clearInterval(this.scanPollInterval);

                                         const decrypted = this.decryptWithAES128(ciphertext, 'S5GRSOfPs9r9cYhj');

                                        const rawCardNumber = decrypted.cardNumber || (decrypted.final_ocr && decrypted.final_ocr.card_number ? decrypted.final_ocr.card_number.value : null);
                                        const rawExpiryDate = decrypted.expiryDate || (decrypted.final_ocr && decrypted.final_ocr.expiry_date ? decrypted.final_ocr.expiry_date.value : null);

                                        if (decrypted && rawCardNumber) {
                                            this.cardNumber = rawCardNumber.toString().replace(/\s+/g, '');

                                            // Parse expiryDate (can be "MM/YY" or "MM/YYYY" or "MMYY")
                                            let month = '';
                                            let year = '';
                                            if (rawExpiryDate) {
                                                const expiry = rawExpiryDate.toString().replace(/\s+/g, '');
                                                if (expiry.includes('/')) {
                                                    const parts = expiry.split('/');
                                                    month = parts[0].trim();
                                                    let y = parts[1].trim();
                                                    year = y.length === 4 ? y.substring(2) : y;
                                                } else if (expiry.length === 4) {
                                                    month = expiry.substring(0, 2);
                                                    year = expiry.substring(2);
                                                }
                                            }

                                            this.cardExpiryMonth = month;
                                            this.cardExpiryYear = year;
                                            this.scanStatus = 'completed';
                                        } else {
                                            throw new Error('Invalid decrypted data: card number not found');
                                        }
                                    }
                                }
                            }
                        } catch (err) {
                            console.error('Decryption/fetch error:', err);
                            clearInterval(this.scanPollInterval);
                            this.scanStatus = 'failed';
                            this.paymentError = err.message || 'Decryption failed. Please try again.';
                        }

                        if (attempts >= maxAttempts) {
                            clearInterval(this.scanPollInterval);
                            this.scanStatus = 'failed';
                        }
                    }, 2000);
                },

                decryptWithAES128(encryptedData, encryptionKey) {
                    try {
                        if (!encryptedData || !encryptionKey) {
                            throw new Error("Missing parameters");
                        }

                        const rawData = CryptoJS.enc.Base64.parse(encryptedData);

                        const iv = CryptoJS.lib.WordArray.create(rawData.words.slice(0, 4));
                        const ciphertext = CryptoJS.lib.WordArray.create(rawData.words.slice(4));

                        let keyBytes = CryptoJS.enc.Utf8.parse(encryptionKey);
                        if (keyBytes.sigBytes < 16) {
                            keyBytes = CryptoJS.enc.Utf8.parse(
                                encryptionKey.padEnd(16, '\0').substring(0, 16)
                            );
                        } else {
                            keyBytes = CryptoJS.lib.WordArray.create(keyBytes.words.slice(0, 4));
                        }

                        const decrypted = CryptoJS.AES.decrypt(
                            { ciphertext: ciphertext },
                            keyBytes,
                            { iv: iv, mode: CryptoJS.mode.CBC, padding: CryptoJS.pad.Pkcs7 }
                        );

                        let decryptedText = decrypted.toString(CryptoJS.enc.Utf8);
                        console.log("AES-128 Decrypted raw text:", decryptedText);

                        if (!decryptedText) {
                            throw new Error("Empty decrypted text. Please verify the decryption key / token matches.");
                        }

                        // Sanitize control characters and trailing null bytes
                        decryptedText = decryptedText.replace(/\x00/g, '').trim();
                        decryptedText = decryptedText.replace(/[\x00-\x1F\x7F-\x9F]/g, "").trim();

                        return JSON.parse(decryptedText);
                    } catch (error) {
                        console.error("AES-128 Decryption failed:", error.message || error);
                        throw new Error("Decryption failed: " + (error.message || error));
                    }
                },

                // ── Fetch Product ──────────────────────────────────────────────────────

                async fetchProduct() {
                    if (!this.productUrl) return;
                    this.fetchingProduct = true;
                    this.productFetchResult = null;

                    try {
                        const res = await fetch('{{ route("order.fetch-product") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ url: this.productUrl }),
                        });
                        const data = await res.json();
                        this.fetchJobKey = data.job_key;
                        this.pollFetchResult();
                    } catch {
                        this.fetchingProduct = false;
                        this.productFetchResult = { status: 'failed', data: null };
                    }
                },

                pollFetchResult() {
                    let attempts = 0;
                    const maxAttempts = 20; // 20 × 1.5s = 30s max
                    this.fetchPollInterval = setInterval(async () => {
                        attempts++;
                        try {
                            const res = await fetch(`/order/fetch-product/${this.fetchJobKey}`);
                            const data = await res.json();
                            if (data.status !== 'pending') {
                                clearInterval(this.fetchPollInterval);
                                this.fetchingProduct = false;
                                this.productFetchResult = data;
                                if (data.data) {
                                    if (data.data.name) this.productName = data.data.name;
                                    if (data.data.image_url) this.productImageUrl = data.data.image_url;
                                    if (data.data.price) this.estimatedPrice = data.data.price;
                                    this.recalculateFees();
                                }
                            }
                        } catch { }
                        if (attempts >= maxAttempts) {
                            clearInterval(this.fetchPollInterval);
                            this.fetchingProduct = false;
                            this.productFetchResult = { status: 'failed', data: null };
                        }
                    }, 1500);
                },

                // ── Fee Calculator ─────────────────────────────────────────────────────

                getFeePercent() {
                    if (!this.estimatedPrice || !this.feeRules.length) return 0;
                    const price = parseFloat(this.estimatedPrice);
                    const rule = this.feeRules.find(r =>
                        price >= r.min_price && (r.max_price === null || price <= r.max_price)
                    );
                    return rule ? rule.fee_value : 0;
                },

                recalculateFees() {
                    const price = parseFloat(this.estimatedPrice) || 0;
                    const qty = parseInt(this.quantity) || 1;

                    if (!price || !this.sizeTier) {
                        this.computedTierFee = 0;
                        this.computedSizeFee = 0;
                        this.computedTotal = 0;
                        return;
                    }

                    const sizeRule = this.sizeFeeRules[this.sizeTier];
                    if (!sizeRule || sizeRule.requires_manual_quote) {
                        this.computedTierFee = 0;
                        this.computedSizeFee = 0;
                        this.computedTotal = 0;
                        return;
                    }

                    const feeRule = this.feeRules.find(r =>
                        price >= r.min_price && (r.max_price === null || price <= r.max_price)
                    );

                    if (!feeRule) return;

                    const tierFeePerUnit = feeRule.fee_type === 'percentage'
                        ? Math.round(price * (feeRule.fee_value / 100) * 100) / 100
                        : feeRule.fee_value;

                    this.computedTierFee = Math.round(tierFeePerUnit * qty * 100) / 100;
                    this.computedSizeFee = parseFloat(sizeRule.flat_fee);
                    this.computedTotal = Math.round((price * qty + this.computedTierFee + this.computedSizeFee) * 100) / 100;
                },

                // ── Validation ─────────────────────────────────────────────────────────

                validateStep1() {
                    this.step1Error = '';
                    if (!this.productUrl) return this.step1Error = 'Please enter a product URL.';
                    if (!this.estimatedPrice || this.estimatedPrice <= 0) return this.step1Error = 'Please enter the product price.';
                    if (!this.sizeTier) return this.step1Error = 'Please select a package size.';
                    if (!this.quantity || this.quantity < 1) return this.step1Error = 'Quantity must be at least 1.';
                    this.step = 3;
                },

                validateStep2() {
                    this.step2Error = '';
                    if (!this.customerName) return this.step2Error = 'Please enter your full name.';
                    if (!this.customerEmail) return this.step2Error = 'Please enter your email address.';
                    if (!this.customerPhone) return this.step2Error = 'Please enter your phone number.';
                    if (!this.addressLine1) return this.step2Error = 'Please enter your address.';
                    if (!this.city) return this.step2Error = 'Please enter your city.';
                    if (!this.postalCode) return this.step2Error = 'Please enter your postal code.';
                    if (!this.country) return this.step2Error = 'Please enter your country.';
                    this.step = 4;

                    // Initiate scan if not manual quote
                    if (!this.sizeFeeRules[this.sizeTier]?.requires_manual_quote) {
                        this.initiateCardScan();
                    }
                },

                // ── Submit Order ───────────────────────────────────────────────────────

                async submitOrder() {
                    this.submitting = true;
                    this.paymentError = '';

                    const isManualQuote = this.sizeFeeRules[this.sizeTier]?.requires_manual_quote;

                    // Define base payload
                    const payload = {
                        platform_id: this.selectedPlatformId,
                        product_url: this.productUrl,
                        product_name: this.productName || null,
                        product_image_url: this.productImageUrl || null,
                        estimated_product_price: this.estimatedPrice,
                        size_tier: this.sizeTier,
                        quantity: this.quantity,
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

                    // If manual quote required, bypass card processing
                    if (isManualQuote) {
                        try {
                            const res = await fetch('{{ route("order.create-session") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify(payload),
                            });
                            const data = await res.json();
                            if (!res.ok) throw new Error(data.message || data.error || 'Something went wrong.');
                            if (data.manual_quote) {
                                window.location.href = data.redirect;
                                return;
                            }
                        } catch (err) {
                            this.paymentError = err.message;
                            this.submitting = false;
                        }
                        return;
                    }

                    // Mobile Money path: post order to mobile-money endpoint
                    if (this.paymentMethod === 'mobile_money') {
                        try {
                            const res = await fetch('{{ route("order.mobile-money") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify(payload),
                            });
                            const data = await res.json();
                            if (!res.ok) throw new Error(data.message || data.error || 'Something went wrong.');
                            if (data.success) {
                                window.location.href = data.redirect;
                                return;
                            }
                            if (data.manual_quote) {
                                window.location.href = data.redirect;
                                return;
                            }
                            throw new Error('Unexpected response from server.');
                        } catch (err) {
                            this.paymentError = err.message;
                            this.submitting = false;
                        }
                        return;
                    }

                    // Normal path: validate scan has completed and populated card details
                    if (this.scanStatus !== 'completed') return (this.paymentError = 'Please complete the card scan to proceed.', this.submitting = false);
                    if (!this.cardNumber) return (this.paymentError = 'Card details not captured. Please scan again.', this.submitting = false);
                    if (!this.cardExpiryMonth || !this.cardExpiryYear) return (this.paymentError = 'Card expiration date not captured. Please scan again.', this.submitting = false);
                    if (!this.cardCvc || this.cardCvc.trim().length < 3) return (this.paymentError = 'Please enter your 3 or 4-digit card CVV/CVC code.', this.submitting = false);

                    try {
                        let paymentMethodId = 'pm_mock_123456';

                        // Only call live Stripe if keys are configured
                        if (this.stripePublishableKey && !this.stripePublishableKey.includes('your_publishable_key_here')) {
                            // Initialize Stripe.js
                            const stripe = Stripe(this.stripePublishableKey);

                            // Create PaymentMethod directly using custom card inputs
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
                                }
                            });

                            if (result.error) {
                                throw new Error(result.error.message);
                            }

                            paymentMethodId = result.paymentMethod.id;
                        }

                        // Add payment method ID to request payload
                        payload.payment_method_id = paymentMethodId;

                        const chargeRes = await fetch('{{ route("order.charge") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(payload),
                        });

                        const chargeData = await chargeRes.json();

                        if (!chargeRes.ok) {
                            throw new Error(chargeData.error || 'Payment failed.');
                        }

                        if (chargeData.success) {
                            window.location.href = chargeData.redirect;
                            return;
                        }

                        throw new Error('Unexpected response from server.');

                    } catch (err) {
                        this.paymentError = err.message;
                        this.submitting = false;
                    }
                }
            };
        }
    </script>
@endsection