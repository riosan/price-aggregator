<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = ['name', 'domain', 'config', 'is_active'];

    
    protected $casts = [
        'config' => 'array',
    ];

    public function offers()
    {
        return $this->hasMany(ProductOffer::class);
    }
}
