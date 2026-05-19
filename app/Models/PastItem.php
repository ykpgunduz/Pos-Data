<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PastItem extends Model
{
    protected $fillable = [
        'cafe_id',
        'order_number',
        'product_id',
        'product_name',
        'quantity',
        'price',
        'cost',
        'tax_rate',
    ];

    /**
     * Geçmiş sipariş ilişkisi
     */
    public function pastOrder(): BelongsTo
    {
        return $this->belongsTo(PastOrder::class, 'order_number', 'order_number')
                    ->where('cafe_id', $this->cafe_id);
    }
}
