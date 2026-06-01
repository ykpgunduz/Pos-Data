<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wastage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WastageController extends Controller
{
    /**
     * Zayiat kaydı oluştur (Ana API üzerinden asenkron çağrılır).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'       => 'required|integer',
            'material_id'   => 'nullable|integer',
            'material_name' => 'required|string|max:255',
            'amount'        => 'required|numeric|min:0',
            'unit_type'     => 'nullable|string',
            'description'   => 'nullable|string',
            'cost'          => 'nullable|numeric|min:0',
            'date'          => 'nullable|date',
        ]);

        $wastage = Wastage::create([
            'cafe_id'       => $request->cafe_id,
            'material_id'   => $request->material_id,
            'material_name' => $request->material_name,
            'amount'        => $request->amount,
            'unit_type'     => $request->unit_type,
            'description'   => $request->description,
            'cost'          => $request->cost ?? 0,
            'date'          => $request->date ?? now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $wastage,
        ], 201);
    }
}
