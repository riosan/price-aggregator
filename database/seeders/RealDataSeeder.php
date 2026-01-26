<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class RealDataSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::updateOrCreate(['slug' => 'gpu'], ['name' => 'Видеокарты']);

        $shop = Shop::updateOrCreate(
            ['domain' => 'hotline.ua'],
            [
                'name' => 'Hotline',
                'config' => ['price_selector' => '.list-item__value-price'],
            ]
        );

        $items = [
            ['name' => 'MSI GeForce RTX 4060 Ti', 'url' => 'https://hotline.ua'],
            ['name' => 'PNY GeForce RTX 4060 Ti', 'url' => 'https://hotline.ua'],
            ['name' => 'Palit GeForce RTX 4060 Ti', 'url' => 'https://hotline.ua'],
        ];

        foreach ($items as $item) {
            // Creating/updating a product
            $product = Product::updateOrCreate(
                ['name' => $item['name']],
                ['category_id' => $category->id]
            );

            ProductOffer::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                ],
                [
                    'url' => $item['url'],
                    'price' => 0,
                ]
            );
        }
    }
}
