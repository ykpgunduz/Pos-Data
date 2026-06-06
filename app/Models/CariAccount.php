<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CariAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'cafe_id',
        'name',
        'customer_type',
        'company_name',
        'tax_number',
        'phone',
        'email',
        'gender',
        'birthday',
        'address',
        'credit_limit',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'birthday'        => 'date',
        'credit_limit'    => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function addBalance($amount)
    {
        $this->current_balance += $amount;
        $this->save();
        return $this;
    }

    public function deductBalance($amount)
    {
        $this->current_balance -= $amount;
        $this->save();
        return $this;
    }
}
