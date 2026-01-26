<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain');
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('url', 1000);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('old_price', 15, 2)->nullable();
            $table->timestamp('last_parsed_at')->nullable();
            $table->timestamps();
        });

        // Добавлена таблица истории цен
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_offer_id')->constrained('product_offers')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем в обратном порядке из-за внешних ключей
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('product_offers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('categories');
    }
};
