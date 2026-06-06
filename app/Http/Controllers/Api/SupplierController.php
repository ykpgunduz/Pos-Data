<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Tedarikçi listesi
     * GET /suppliers/list?cafe_id=X
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['cafe_id' => 'required|integer']);

        $suppliers = Supplier::where('cafe_id', $request->cafe_id)
            ->orderBy('company_name')
            ->get();

        return response()->json($suppliers);
    }

    /**
     * Tedarikçi detayı
     */
    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::with('expenses')->findOrFail($id);
        return response()->json($supplier);
    }

    /**
     * Yeni tedarikçi ekle
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'       => 'required|integer',
            'company_name'  => 'required|string|max:255',
            'contact_name'  => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string',
            'tax_office'    => 'nullable|string|max:255',
            'tax_number'    => 'nullable|string|max:50',
            'supplier_type' => 'nullable|string|max:100',
            'notes'         => 'nullable|string',
        ]);

        $supplier = Supplier::create([
            'cafe_id'       => $request->cafe_id,
            'company_name'  => $request->company_name,
            'contact_name'  => $request->contact_name,
            'phone'         => $request->phone,
            'email'         => $request->email,
            'address'       => $request->address,
            'tax_office'    => $request->tax_office,
            'tax_number'    => $request->tax_number,
            'supplier_type' => $request->supplier_type ?? 'Diğer',
            'notes'         => $request->notes,
        ]);

        return response()->json($supplier, 201);
    }

    /**
     * Tedarikçi güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $request->validate([
            'company_name'  => 'sometimes|string|max:255',
            'contact_name'  => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string',
            'tax_office'    => 'nullable|string|max:255',
            'tax_number'    => 'nullable|string|max:50',
            'supplier_type' => 'nullable|string|max:100',
            'notes'         => 'nullable|string',
            'is_active'     => 'nullable|boolean',
        ]);

        $supplier->update($request->only([
            'company_name', 'contact_name', 'phone', 'email',
            'address', 'tax_office', 'tax_number', 'supplier_type',
            'notes', 'is_active',
        ]));

        return response()->json($supplier);
    }

    /**
     * Tedarikçi sil
     */
    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();
        return response()->json(['message' => 'Tedarikçi silindi.']);
    }

    /**
     * Tedarikçiye ödeme yap (borç azalt)
     */
    public function addPayment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string',
        ]);

        $supplier = Supplier::findOrFail($id);
        $supplier->increment('current_balance', (float) $request->amount);

        return response()->json([
            'message'         => 'Ödeme kaydedildi.',
            'current_balance' => $supplier->fresh()->current_balance,
        ]);
    }
}
