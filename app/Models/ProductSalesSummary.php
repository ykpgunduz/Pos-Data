<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSalesSummary extends Model
{
    protected $fillable = [
        'cafe_id',
        'date',
        'product_id',
        'product_name',
        'quantity_sold',
        'total_revenue',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
