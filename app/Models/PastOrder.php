<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PastOrder extends Model
{
    protected $fillable = [
        'cafe_id',
        'order_number',
        'table_number',
        'cari_account_id',
        'customer',
        'customer_male',
        'customer_female',
        'customer_child',
        'total_amount',
        'net_amount',
        'cash',
        'card',
        'iban',
        'treat',
        'discount',
        'self_treat',
        'closed_by',
        'opened_by_name',
        'closed_by_name',
        'created_at',
        'updated_at',
    ];

    protected $attributes = [
        'customer_male'   => 0,
        'customer_female' => 0,
        'customer_child'  => 0,
    ];

    /**
     * Siparişe ait geçmiş ürünler
     */
    public function items(): HasMany
    {
        return $this->hasMany(PastItem::class, 'order_number', 'order_number');
    }
}
