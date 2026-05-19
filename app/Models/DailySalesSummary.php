<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesSummary extends Model
{
    protected $fillable = [
        'cafe_id',
        'date',
        'total_turnover',
        'total_cost',
        'total_orders',
        'total_net_amount',
        'total_tax_amount',
        'total_cash',
        'total_card',
        'total_iban',
        'total_treat',
        'total_discount',
        'total_customers',
        'total_customer_male',
        'total_customer_female',
        'total_customer_child',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
