<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ParserService
{
    /**
     * Пакетный парсинг цен через поисковую выдачу
     */
    public function parseBatch($offers)
    {
        $responses = Http::pool(fn (Pool $pool) => $offers->map(function ($offer) use ($pool) {

            $searchUrl = 'https://hotline.ua/ua/computer/videokarty/?q='.urlencode($offer->product->name);

            return $pool->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->timeout(15)->get($searchUrl);
        })
        );

        foreach ($responses as $index => $response) {
            if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                $this->processSearchResult($offers[$index], $response->body());
            } else {
                $error = ($response instanceof \Exception) ? $response->getMessage() : 'Status: '.$response->status();
                Log::error("Ошибка для {$offers[$index]->product->name}: {$error}");
            }
        }
    }

    /**
     * The logic of extracting data from search results
     */
    private function processSearchResult($offer, $html)
    {
        $crawler = new Crawler($html);

        // 1. We first search for the product container in the search (usually .list-item)
        $item = $crawler->filter('.list-item')->first();

        if ($item->count() > 0) {
            // 2. We are looking for the price ONLY within this block and ONLY in a specific class.
            $priceNode = $item->filter('.list-item__value-price');

            if ($priceNode->count() > 0) {
                // We extract the text, clear it of unnecessary nested tags (if any)
                $priceRaw = $priceNode->first()->text();

                // 3. Improved cleaning: we remove everything except the numbers
                // We take the last 5-6 digits if the site has combined the price with something else
                $priceCleaned = preg_replace('/[^\d]/', '', $priceRaw);

                // If the number is too long (as in your case), we take only what looks like the price
                // (this is a temporary crutch until we find the exact nested selector)
                if (strlen($priceCleaned) > 8) {
                    // If 2003836537 arrives, we try to pick up the tail
                    $newPrice = (float) substr($priceCleaned, -5);
                } else {
                    $newPrice = (float) $priceCleaned;
                }

                if ($newPrice > 0) {
                    $offer->update([
                        'old_price' => $offer->price,
                        'price' => $newPrice,
                        'last_parsed_at' => now(),
                    ]);

                    \App\Models\PriceHistory::create([
                        'product_offer_id' => $offer->id,
                        'price' => $newPrice,
                    ]);

                    \Log::info("The price has been successfully cleared for {$offer->product->name}: {$newPrice}");
                }
            }
        }
    }
}
