<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'cafe_id',
        'company_name',
        'contact_name',
        'phone',
        'email',
        'address',
        'tax_office',
        'tax_number',
        'supplier_type',
        'notes',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
