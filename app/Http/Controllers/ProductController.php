<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
   public function index()
{
    $products = \App\Models\Product::with(['offers.shop', 'offers.priceHistory' => function($query) {
        $query->orderBy('created_at', 'asc'); // Sorting the history from old to new
    }])->get()->map(function ($product) {
        $product->best_offer = $product->offers->sortBy('price')->first();
        return $product;
    });

    return view('welcome', compact('products'));
}

}
