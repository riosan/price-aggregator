<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Price monitoring 2026</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">

    <div class="max-w-6xl mx-auto py-12 px-4">
        <!-- Project header -->
        <header class="mb-12 flex justify-between items-center">
            <div>
                <h1 class="text-4xl font-extrabold text-indigo-600 tracking-tight">
                    {{ config('app.name') }}
                </h1>
                <p class="text-gray-500 mt-2 text-lg">High-performance aggregator on Laravel 12 & Swoole</p>
            </div>
            <div class="text-right">
                <span class="bg-indigo-600 text-white px-5 py-2 rounded-xl text-sm font-bold shadow-lg shadow-indigo-200">
                    Products: {{ $products->count() }}
                </span>
            </div>
        </header>

       <!-- Product grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($products as $product)
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-2xl transition-all duration-500">
                    <div class="p-8">
                        <!-- Name -->
                        <h2 class="text-xl font-bold text-gray-800 mb-4 h-14 line-clamp-2 leading-tight">
                            {{ $product->name }}
                        </h2>
                        
                        @if($product->best_offer)
                           <!-- The price block -->
                            <div class="flex items-center gap-3 mb-4">
                                <span class="text-3xl font-black text-gray-900 tracking-tighter">
                                    {{ number_format($product->best_offer->price, 0, '.', ' ') }} ₴
                                </span>

                                <!-- Price change indication -->
                                @if($product->best_offer->old_price > 0)
                                    @php
                                        $diff = $product->best_offer->price - $product->best_offer->old_price;
                                        $isDown = $diff < 0;
                                        $percent = abs(round(($diff / $product->best_offer->old_price) * 100));
                                    @endphp
                                    
                                    @if($diff != 0)
                                        <span class="flex items-center px-2 py-1 rounded-lg text-sm font-black {{ $isDown ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}">
                                            {!! $isDown ? '↓' : '↑' !!} {{ $percent }}%
                                        </span>
                                    @endif
                                @endif
                            </div>

                            <!-- Chart area-->
                            <div class="mt-4 mb-6 border-b border-dashed border-gray-100 pb-6" style="height: 80px; position: relative;">
                                @if($product->best_offer->priceHistory && $product->best_offer->priceHistory->count() > 1)
                                    <canvas class="product-chart" 
                                        data-prices="{{ json_encode($product->best_offer->priceHistory->pluck('price')->toArray()) }}"
                                        data-labels="{{ json_encode($product->best_offer->priceHistory->map(fn($h) => \Carbon\Carbon::parse($h->created_at)->format('d.m'))->values()->toArray()) }}">
                                    </canvas>
                                @else
                                    <div class="h-full flex items-center justify-center text-gray-300 text-xs italic">
                                       We accumulate the price history...
                                    </div>
                                @endif
                            </div>

                            <!-- Meta data -->
                            <div class="space-y-3 mb-8 text-sm">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400 font-medium">Shop</span>
                                    <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-md font-bold">{{ $product->best_offer->shop->name }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400 font-medium">Updated</span>
                                    <span class="text-gray-600 font-semibold italic">
                                        {{ $product->best_offer->last_parsed_at ? \Carbon\Carbon::parse($product->best_offer->last_parsed_at)->diffForHumans() : 'just now' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Link -->
                            <a href="{{ $product->best_offer->url }}" target="_blank" 
                               class="group block w-full text-center bg-gray-900 text-white py-4 rounded-2xl font-bold hover:bg-indigo-600 transition-all duration-300 shadow-lg shadow-gray-200 hover:shadow-indigo-200">
                                Open on the website <span class="group-hover:ml-2 transition-all">→</span>
                            </a>
                        @else
                            <div class="py-12 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                                <p class="text-gray-400 text-sm font-medium uppercase tracking-widest">There are no offers</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <footer class="mt-24 text-center border-t border-gray-100 pt-10">
            <p class="text-gray-400 text-sm font-medium tracking-wide uppercase">
                &copy; 2026 {{ config('app.name') }} | Technology: Laravel Octane & Redis
            </p>
        </footer>
    </div>

    <!-- Global Chart initialization script -->
    <script>
        window.addEventListener('load', function() {
            if (typeof Chart === 'undefined') {
                console.error('Ошибка: Библиотека Chart.js не загружена.');
                return;
            }

            document.querySelectorAll('.product-chart').forEach(canvas => {
                try {
                    const rawPrices = canvas.getAttribute('data-prices');
                    const rawLabels = canvas.getAttribute('data-labels');
                    
                    if (!rawPrices || !rawLabels) return;

                    const prices = JSON.parse(rawPrices);
                    const labels = JSON.parse(rawLabels);
                    
                    new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: prices,
                                borderColor: '#6366f1',
                                borderWidth: 3,
                                pointRadius: 0,
                                pointHoverRadius: 5,
                                pointBackgroundColor: '#6366f1',
                                tension: 0.4,
                                fill: true,
                                backgroundColor: (context) => {
                                    const chart = context.chart;
                                    const {ctx, chartArea} = chart;
                                    if (!chartArea) return null;
                                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                    gradient.addColorStop(0, 'rgba(255, 255, 255, 0)');
                                    gradient.addColorStop(1, 'rgba(99, 102, 241, 0.1)');
                                    return gradient;
                                }
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#111827',
                                    titleFont: { size: 10 },
                                    bodyFont: { size: 12, weight: 'bold' },
                                    padding: 10,
                                    displayColors: false
                                }
                            },
                            scales: { 
                                x: { display: false }, 
                                y: { 
                                    display: false,
                                    suggestedMin: Math.min(...prices) * 0.95,
                                    suggestedMax: Math.max(...prices) * 1.05
                                } 
                            }
                        }
                    });
                } catch (e) {
                    console.error('Error initializing a specific schedule:', e);
                }
            });
        });
    </script>
</body>
</html>
