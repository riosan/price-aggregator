<?php

namespace App\Services;

use App\Models\PriceHistory;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ParserService
{
    public function parseBatch($offers): void
    {
        $responses = Http::pool(fn (Pool $pool) => $offers->map(function ($offer) use ($pool) {
            // URL теперь гибкий, но с рабочей структурой
            $searchUrl = "https://{$offer->shop->domain}/ua/computer/videokarty/?q=".urlencode($offer->product->name);

            return $pool->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->timeout(15)->get($searchUrl);
        }));

        foreach ($responses as $index => $response) {
            if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                $this->processSearchResult($offers[$index], $response->body());
            } else {
                Log::error("Request failed for {$offers[$index]->product->name}");
            }
        }
    }

    private function processSearchResult($offer, $html): void
    {
        $crawler = new Crawler($html);

        // БЕРЕМ ИЗ БД, но если там пусто или нет точки — исправляем на лету
        $config = $offer->shop->config;
        $itemClass = $config['item_selector'] ?? '.list-item';
        $priceClass = $config['price_selector'] ?? '.list-item__value-price';

        // Гарантируем наличие точки для селектора класса
        $itemSelector = str_starts_with($itemClass, '.') ? $itemClass : ".$itemClass";
        $priceSelector = str_starts_with($priceClass, '.') ? $priceClass : ".$priceClass";

        $item = $crawler->filter($itemSelector)->first();

        if ($item->count() > 0) {
            $priceNode = $item->filter($priceSelector);

            if ($priceNode->count() > 0) {
                $priceRaw = $priceNode->first()->text();

                // Чистим как в старом добром методе
                $priceCleaned = preg_replace('/[^\d]/', '', $priceRaw);

                if (strlen($priceCleaned) > 8) {
                    $newPrice = (float) substr($priceCleaned, -5);
                } else {
                    $newPrice = (float) $priceCleaned;
                }

                if ($newPrice > 0) {
                    // Убрали проверку на неравенство, чтобы ты видел обновления в БД сразу
                    $offer->update([
                        'old_price' => $offer->price,
                        'price' => $newPrice,
                        'last_parsed_at' => now(),
                    ]);

                    PriceHistory::create([
                        'product_offer_id' => $offer->id,
                        'price' => $newPrice,
                    ]);

                    Log::info("Successfully parsed {$offer->product->name}: {$newPrice}");
                }
            } else {
                Log::warning("Price node not found with selector: $priceSelector");
            }
        } else {
            Log::warning("Item container not found with selector: $itemSelector");
        }
    }
}
