<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'cafe_id',
        'supplier_id',
        'category',
        'title',
        'description',
        'amount',
        'expense_date',
        'is_recurring',
        'recurring_day',
        'payment_method',
        'is_paid',
        'added_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'expense_date' => 'date',
        'is_recurring' => 'boolean',
        'is_paid'      => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
