<?php

namespace App\Services;

use App\Models\PriceHistory;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ParserService
{
    /**
     * Batch parse prices using concurrent search requests.
     */
    public function parseBatch($offers): void
    {
        $responses = Http::pool(fn (Pool $pool) => $offers->map(function ($offer) use ($pool) {
            // Build search URL using the shop domain from database
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

    /**
     * Process single search result HTML and update price data.
     */
    private function processSearchResult($offer, $html): void
    {
        $crawler = new Crawler($html);

        // Fetch selectors from shop configuration or use defaults
        $config = $offer->shop->config;
        $itemClass = $config['item_selector'] ?? '.list-item';
        $priceClass = $config['price_selector'] ?? '.list-item__value-price';

        // Ensure selectors start with a dot for CSS class identification
        $itemSelector = str_starts_with($itemClass, '.') ? $itemClass : ".$itemClass";
        $priceSelector = str_starts_with($priceClass, '.') ? $priceClass : ".$priceClass";

        // Filter the first matching product container
        $item = $crawler->filter($itemSelector)->first();

        if ($item->count() > 0) {
            // Locate the price node within the item container
            $priceNode = $item->filter($priceSelector);

            if ($priceNode->count() > 0) {
                $priceRaw = $priceNode->first()->text();

                // Clean price string by removing all non-numeric characters
                $priceCleaned = preg_replace('/[^\d]/', '', $priceRaw);

                // Handle cases with concatenated IDs/prices (hotline-specific logic)
                if (strlen($priceCleaned) > 8) {
                    $newPrice = (float) substr($priceCleaned, -5);
                } else {
                    $newPrice = (float) $priceCleaned;
                }

                if ($newPrice > 0) {
                    // Update current offer and record price history
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
