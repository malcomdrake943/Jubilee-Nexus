@extends('layouts.app')

@section('title', 'Jubilee Store')
@section('meta_description', 'Shop curated global products fulfilled directly through Jubilee Direct & CJdropshipping with international shipping.')

@section('content')
<div class="max-w-6xl mx-auto px-4">
    <!-- Hero Header -->
    <div class="text-center mb-10 animate-fade-in">
        <div class="inline-flex items-center gap-2 bg-white/10 rounded-full px-4 py-2 text-white/70 text-xs font-medium mb-5 border border-white/20">
            <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
            Direct Storefront · Worldwide Shipping
        </div>
        <h1 class="text-4xl md:text-5xl font-display font-extrabold text-white leading-tight mb-4">
            Shop Our <span class="gradient-text">Curated Store</span>
        </h1>
        <p class="text-white/60 text-base md:text-lg max-w-2xl mx-auto">
            Order directly from our verified catalog with transparent pricing, real-time shipping quotes, and automated global fulfillment.
        </p>
    </div>

    <!-- Search & Filter Bar -->
    <div class="glass-light rounded-3xl p-5 md:p-6 shadow-2xl mb-8 animate-slide-up">
        <form action="{{ route('store.index') }}" method="GET" class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
            <div class="flex-1 relative">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search products, categories, or SKU..."
                    class="input-field w-full pl-11 pr-4 py-3 rounded-xl text-gray-800 text-sm">
                <svg class="w-5 h-5 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            @if($categories->isNotEmpty())
                <select name="category" class="input-field px-4 py-3 rounded-xl text-gray-800 text-sm md:w-52">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            @endif

            <select name="sort" class="input-field px-4 py-3 rounded-xl text-gray-800 text-sm md:w-44">
                <option value="featured" @selected($sort === 'featured')>Featured</option>
                <option value="newest" @selected($sort === 'newest')>Newest</option>
                <option value="price_asc" @selected($sort === 'price_asc')>Price: Low to High</option>
                <option value="price_desc" @selected($sort === 'price_desc')>Price: High to Low</option>
            </select>

            <button type="submit" class="btn-primary text-white px-6 py-3 rounded-xl text-sm font-bold whitespace-nowrap">
                Filter Store
            </button>
        </form>

        @if($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-200/70">
                <a href="{{ route('store.index', array_filter(['q' => $search, 'sort' => $sort])) }}"
                    class="px-3 py-1 rounded-full text-xs font-semibold transition-colors {{ $category === '' ? 'bg-brand-600 text-white' : 'bg-white/80 text-gray-700 hover:bg-white' }}">
                    All
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('store.index', array_filter(['category' => $cat, 'q' => $search, 'sort' => $sort])) }}"
                        class="px-3 py-1 rounded-full text-xs font-semibold transition-colors {{ $category === $cat ? 'bg-brand-600 text-white' : 'bg-white/80 text-gray-700 hover:bg-white' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Product Grid -->
    @if($products->isEmpty())
        <div class="glass-light rounded-3xl p-12 text-center shadow-2xl">
            <div class="w-16 h-16 bg-brand-50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl">🛍️</div>
            <h2 class="text-xl font-display font-bold text-gray-900 mb-2">No products found</h2>
            <p class="text-gray-500 text-sm max-w-md mx-auto mb-6">
                @if($search || $category)
                    No products matched your current filter. Try clearing your search or checking all categories.
                @else
                    Our store catalog is being stocked. You can still order any item by link using our main Order tool!
                @endif
            </p>
            <div class="flex items-center justify-center gap-3 flex-wrap">
                @if($search || $category)
                    <a href="{{ route('store.index') }}" class="px-5 py-3 rounded-xl border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-white transition-colors">
                        Reset Filters
                    </a>
                @endif
                <a href="{{ route('order.index') }}" class="btn-primary text-white px-6 py-3 rounded-xl text-sm font-bold">
                    Order via Custom Link →
                </a>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
            x-data="storeCatalogCart()">
            @foreach($products as $product)
                <div class="glass-light rounded-2xl overflow-hidden shadow-xl hover:shadow-2xl transition-all duration-300 group flex flex-col justify-between border border-white/40 hover:-translate-y-1">
                    <a href="{{ route('store.show', $product) }}" class="block">
                        <div class="aspect-square bg-white relative overflow-hidden flex items-center justify-center p-4">
                            @if($product->display_image_url)
                                <img src="{{ $product->display_image_url }}" alt="{{ $product->name }}"
                                    class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300">
                            @else
                                <span class="text-5xl">📦</span>
                            @endif
                            @if($product->is_featured)
                                <span class="absolute top-3 left-3 bg-amber-500 text-white text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full shadow">
                                    Featured
                                </span>
                            @endif
                        </div>

                        <div class="p-5 pb-3">
                            @if($product->category_name)
                                <p class="text-[11px] font-bold uppercase tracking-wider text-brand-500 mb-1">
                                    {{ $product->category_name }}
                                </p>
                            @endif
                            <h3 class="font-display font-bold text-gray-900 text-sm leading-snug line-clamp-2 min-h-[2.5rem] group-hover:text-brand-600 transition-colors">
                                {{ $product->name }}
                            </h3>
                        </div>
                    </a>

                    <div class="px-5 pb-5 pt-3 border-t border-gray-200/60 mt-auto space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[11px] text-gray-500 font-medium">Price</p>
                                <p class="text-lg font-extrabold text-brand-700">${{ number_format((float) $product->retail_price, 2) }}</p>
                            </div>
                            <a href="{{ route('store.show', $product) }}" class="text-xs font-semibold text-brand-600 hover:text-brand-800">
                                Buy Now →
                            </a>
                        </div>

                        <button type="button"
                            @click="add({{ $product->id }}, {{ json_encode($product->cj_vid) }})"
                            :disabled="addingId === {{ $product->id }}"
                            class="btn-primary w-full text-white text-xs font-bold px-4 py-2.5 rounded-xl flex items-center justify-center gap-1.5 disabled:opacity-60">
                            <span x-text="addingId === {{ $product->id }} ? 'Adding...' : (addedId === {{ $product->id }} ? '✓ Added to Cart' : '🛒 Add to Cart')"></span>
                        </button>
                    </div>
                </div>
            @endforeach

            <!-- Floating Add-to-Cart Toast -->
            <div x-show="toastMessage" x-cloak
                class="fixed bottom-6 right-6 z-50 glass-light rounded-2xl shadow-2xl border border-brand-200 px-5 py-4 flex items-center gap-4 max-w-sm animate-slide-up">
                <div class="text-2xl">🛒</div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-gray-900 truncate" x-text="toastMessage"></p>
                    <a href="{{ route('store.cart') }}" class="text-xs font-bold text-brand-600 hover:text-brand-800">
                        View Cart & Checkout →
                    </a>
                </div>
                <button type="button" @click="toastMessage = ''" class="text-gray-400 hover:text-gray-600 text-xs font-bold">✕</button>
            </div>
        </div>

        <script>
        function storeCatalogCart() {
            return {
                addingId: null,
                addedId: null,
                toastMessage: '',
                toastTimer: null,
                async add(productId, vid) {
                    this.addingId = productId;
                    try {
                        const res = await fetch('{{ route("store.cart.add") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                store_product_id: productId,
                                vid: vid || null,
                                quantity: 1,
                            }),
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            window.updateNavCartCount(data.cart_count);
                            this.addedId = productId;
                            this.toastMessage = data.message || 'Added to cart!';
                            if (this.toastTimer) clearTimeout(this.toastTimer);
                            this.toastTimer = setTimeout(() => {
                                this.toastMessage = '';
                                if (this.addedId === productId) this.addedId = null;
                            }, 3500);
                        }
                    } catch {}
                    this.addingId = null;
                }
            };
        }
        </script>

        <div class="mt-8">
            {{ $products->links() }}
        </div>
    @endif
</div>
@endsection
