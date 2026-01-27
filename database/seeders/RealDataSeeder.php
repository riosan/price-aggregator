<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class RealDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create or update the category
        $category = Category::updateOrCreate(
            ['slug' => 'gpu'],
            ['name' => 'Video Cards']
        );

        // 2. Create or update the shop with full parsing configuration
        $shop = Shop::updateOrCreate(
            ['domain' => 'hotline.ua'],
            [
                'name' => 'Hotline',
                'config' => [
                    // CSS selectors must include dots for the Crawler to identify them as classes
                    'item_selector' => '.list-item',
                    'price_selector' => '.list-item__value-price',
                ],
            ]
        );

        // 3. Define the list of products for monitoring
        $items = [
            [
                'name' => 'MSI GeForce RTX 4060 Ti',
                'url' => 'https://hotline.ua',
            ],
            [
                'name' => 'PNY GeForce RTX 4060 Ti',
                'url' => 'https://hotline.ua',
            ],
            [
                'name' => 'Palit GeForce RTX 4060 Ti',
                'url' => 'https://hotline.ua',
            ],
        ];

        // 4. Iterate through items to create products and link them to the shop
        foreach ($items as $item) {
            $product = Product::updateOrCreate(
                ['name' => $item['name']],
                ['category_id' => $category->id]
            );

            // Search by composite key (product_id + shop_id) to avoid duplicates
            ProductOffer::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                ],
                [
                    'url' => $item['url'],
                    'price' => 0, // Initial price, will be updated by the ParserService
                ]
            );
        }
    }
}
