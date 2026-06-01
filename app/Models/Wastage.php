<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wastage extends Model
{
    use HasFactory;

    protected $fillable = [
        'cafe_id',
        'material_id',
        'material_name',
        'amount',
        'unit_type',
        'description',
        'cost',
        'date'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cost' => 'decimal:2',
        'date' => 'date'
    ];
}
