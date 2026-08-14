<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Jubilee Direct') – Global Purchase Forwarding</title>
    <meta name="description" content="@yield('meta_description', 'Jubilee Direct — buy from Amazon, BestBuy and more. We purchase and ship internationally on your behalf.')">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50:  '#f0f5fa',
                            100: '#dce6f2',
                            200: '#bdcde2',
                            300: '#90abcb',
                            400: '#5e83af',
                            500: '#0f2b48',
                            600: '#0b1f35',
                            700: '#081626',
                            800: '#050e19',
                            900: '#03070f',
                            950: '#010306',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.4s ease-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
                        slideUp: { '0%': { opacity: 0, transform: 'translateY(16px)' }, '100%': { opacity: 1, transform: 'translateY(0)' } },
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Stripe.js -->
    <script src="https://js.stripe.com/v3/"></script>

    <style>
        [x-cloak] { display: none !important; }

        .glass {
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.12);
        }

        .glass-light {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(15, 43, 72, 0.12);
        }

        .gradient-text {
            background: linear-gradient(135deg, #c5a059, #e5c07b, #a37d35);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0f2b48, #163e65);
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(15, 43, 72, 0.4);
            border: 1px solid rgba(197, 160, 89, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #c5a059, #a37d35);
            color: #050e1e;
            box-shadow: 0 8px 25px rgba(197, 160, 89, 0.4);
            border-color: #c5a059;
            transform: translateY(-1px);
        }
        .btn-primary:active { transform: translateY(0); }

        .input-field {
            background: rgba(255,255,255,0.9);
            border: 1.5px solid rgba(15, 43, 72, 0.2);
            transition: all 0.2s ease;
        }
        .input-field:focus {
            border-color: #0f2b48;
            box-shadow: 0 0 0 3px rgba(15, 43, 72, 0.15);
            outline: none;
        }

        .step-dot {
            transition: all 0.3s ease;
        }
        .step-dot.active {
            background: linear-gradient(135deg, #0f2b48, #163e65);
            box-shadow: 0 4px 12px rgba(15, 43, 72, 0.5);
            border: 1px solid rgba(197, 160, 89, 0.4);
        }

        .fee-card {
            background: linear-gradient(135deg, rgba(15,43,72,0.08), rgba(197,160,89,0.08));
            border: 1px solid rgba(15, 43, 72, 0.2);
            transition: all 0.3s ease;
        }

        .platform-badge {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* Smooth height transition for fee breakdown */
        .fee-breakdown { transition: all 0.3s ease; }

        /* Loading spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 0.8s linear infinite; }

        .status-timeline-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 3px;
        }
    </style>
</head>
<body class="min-h-screen font-sans" style="background: linear-gradient(135deg, #050e1e, #0c1c38, #182e56);">

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 py-4 px-6">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="{{ route('order.index') }}" class="flex items-center gap-3 group">
                <img src="/images/logo.jpg" alt="Jubilee Direct Logo" class="w-10 h-10 object-contain rounded-xl bg-white p-1 border border-white/20 shadow-sm">
                <span class="text-white font-display font-bold text-xl tracking-tight">Jubilee<span class="gradient-text">Direct</span></span>
            </a>
            <div class="flex items-center gap-4">
                <a href="{{ route('order.index') }}" class="text-white/70 hover:text-white text-sm font-medium transition-colors">Order</a>
                <a href="#" onclick="document.getElementById('resend-modal').classList.remove('hidden')" class="text-white/70 hover:text-white text-sm font-medium transition-colors">Track Order</a>
            </div>
        </div>
    </nav>

    <!-- Track Order Modal -->
    <div id="resend-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.7);">
        <div class="glass-light rounded-2xl p-8 max-w-md w-full shadow-2xl animate-slide-up">
            <h3 class="text-xl font-display font-bold text-gray-900 mb-2">Track Your Order</h3>
            <p class="text-gray-500 text-sm mb-6">Enter your order number to track your package status.</p>
            <form action="{{ route('order.track-public') }}" method="GET" class="space-y-4">
                <input type="text" name="order_number" required placeholder="Order Number (e.g. PP-1042)"
                    class="input-field w-full px-4 py-3 rounded-xl text-gray-800 text-sm">
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('resend-modal').classList.add('hidden')"
                        class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 btn-primary text-white py-3 rounded-xl text-sm font-semibold">
                        Track Order
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <main class="pt-24 pb-16 min-h-screen">
        @if ($errors->any())
            <div class="max-w-2xl mx-auto px-4 mb-6">
                <div class="bg-red-500/10 border border-red-500/20 text-red-200 rounded-2xl p-4 text-sm font-medium">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="py-8 px-6 border-t border-white/10">
        <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
            <p class="text-white/40 text-sm">© {{ date('Y') }} Jubilee Direct. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
