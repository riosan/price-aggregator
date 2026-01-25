<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProductOffer;
use App\Services\ParserService;

class ParsePrices extends Command
{
    /**
     * Имя и параметры команды для запуска: php artisan parse:prices
     */
    protected $signature = 'parse:prices';

    /**
     * Описание команды (видно в списке php artisan list)
     */
    protected $description = 'Запуск многопоточного парсинга цен для всех предложений';

    /**
     * Основная логика команды
     */
    public function handle(ParserService $parserService)
    {
        $this->info('--- Запуск парсинга: ' . now()->toDateTimeString() . ' ---');

        // 1. Получаем все активные предложения вместе с конфигами магазинов
        $offers = ProductOffer::with('shop')->get();

        if ($offers->isEmpty()) {
            $this->warn('Предложений для парсинга не найдено. Сначала добавьте данные через Seeder.');
            return;
        }

        $this->info("Найдено предложений: {$offers->count()}");

        // 2. Разбиваем на пачки (chunks) по 10 штук для асинхронной обработки
        // Это позволяет парсить 10 сайтов одновременно в одном потоке
        $bar = $this->output->createProgressBar($offers->count());
        $bar->start();

        $offers->chunk(10)->each(function ($chunk) use ($parserService, $bar) {
            $parserService->parseBatch($chunk);
            $bar->advance($chunk->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info('--- Парсинг успешно завершен ---');
    }
}
