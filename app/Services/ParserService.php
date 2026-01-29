<?php

namespace App\Services;

use App\Models\PriceHistory;
use App\Models\ProductOffer; // Добавили прямой импорт
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ParserService
{
    public function parseBatch($offers): void
    {
        $responses = Http::pool(fn (Pool $pool) => $offers->map(function ($offer) use ($pool) {
            $searchUrl = "https://{$offer->shop->domain}/ua/computer/videokarty/?q=" . urlencode($offer->product->name);

            return $pool->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])
            ->withOptions(['allow_redirects' => true])
            ->timeout(15)
            ->get($searchUrl);
        }));

        foreach ($responses as $index => $response) {
            if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                // ПЕРЕДАЕМ ID ВМЕСТО ОБЪЕКТА, ЧТОБЫ ИСКЛЮЧИТЬ КЭШ ПАМЯТИ
                $this->processSearchResult($offers[$index]->id, $response->body());
            }
        }
    }

    private function processSearchResult($offerId, $html): void
    {
        // 1. ДОСТАЕМ СВЕЖАЙШИЙ ОБЪЕКТ ИЗ БАЗЫ ПО ID ПРЯМО СЕЙЧАС
        $offer = ProductOffer::with('shop', 'product')->find($offerId);
        if (!$offer) return;

        $crawler = new Crawler($html);
        $config = $offer->shop->config;
        
        $itemClass = $config['item_selector'] ?? '.list-item';
        $priceClass = $config['price_selector'] ?? '.list-item__value-price';
        $itemSelector = str_starts_with($itemClass, '.') ? $itemClass : ".$itemClass";
        $priceSelector = str_starts_with($priceClass, '.') ? $priceClass : ".$priceClass";

        $item = $crawler->filter($itemSelector)->first();

        if ($item->count() > 0) {
            $priceNode = $item->filter($priceSelector);

            if ($priceNode->count() > 0) {
                $priceRaw = $priceNode->first()->text();
                $priceCleaned = preg_replace('/[^\d]/', '', str_replace(["\xc2\xa0", "\xa0", " "], '', $priceRaw));

                $newPrice = (strlen($priceCleaned) > 8) ? (float) substr($priceCleaned, -5) : (float) $priceCleaned;

                if ($newPrice > 0) {
                    $oldPrice = (float) $offer->price;

                    // ЛОГ ДЛЯ ПРОВЕРКИ (СМОТРИ ЕГО В ТЕРМИНАЛЕ)
                    Log::info("REALTIME CHECK [ID:{$offerId}]: DB={$oldPrice}, SITE={$newPrice}");

                    $offer->update([
                        'old_price' => $oldPrice,
                        'price' => $newPrice,
                        'last_parsed_at' => now(),
                    ]);

                    PriceHistory::create([
                        'product_offer_id' => $offer->id,
                        'price' => $newPrice,
                    ]);

                    // СРАВНИВАЕМ ЧЕРЕЗ ABS ДЛЯ ТОЧНОСТИ
                    if (abs($oldPrice - $newPrice) > 0.1) {
                        Log::info("!!! SUCCESS: Price change. Sending Telegram for ID:{$offerId}");
                        $this->sendTelegramNotification($offer->product->name, $oldPrice, $newPrice, $offer->url);
                    }
                }
            }
        }
    }

    private function sendTelegramNotification($productName, $oldPrice, $newPrice, $url): void
    {
        $token = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            Log::error("TELEGRAM ERROR: Keys missing in config!");
            return;
        }

        $status = ($newPrice < $oldPrice) ? "📉 *Price Drop!*" : "📈 *Price Increase*";
        
        $message = "{$status}\n\n" .
                   "*Product:* {$productName}\n" .
                   "*Old Price:* " . number_format($oldPrice, 0, '.', ' ') . " ₴\n" .
                   "*New Price:* " . number_format($newPrice, 0, '.', ' ') . " ₴";

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[['text' => '🚀 Open Product Page', 'url' => $url]]]
            ])
        ]);

        if (!$response->successful()) {
            Log::error("TELEGRAM API FAIL: " . $response->body());
        }
    }
}
