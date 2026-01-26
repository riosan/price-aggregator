<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    protected $casts = [
        'created_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    // Specifying the exact name of the migration table
    protected $table = 'price_history';

    // Disabling automatic updated_at columns, since we only have created_at
    public $timestamps = false;

    protected $fillable = ['product_offer_id', 'price'];

    public function offer()
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }
}
