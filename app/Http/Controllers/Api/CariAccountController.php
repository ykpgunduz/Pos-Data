<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CariAccount;
use App\Models\PastOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CariAccountController extends Controller
{
    /**
     * Cari hesap listesi
     * GET /cari-accounts/list?cafe_id=X&is_active=X
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['cafe_id' => 'required|integer']);

        $query = CariAccount::where('cafe_id', $request->cafe_id);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $accounts = $query->orderBy('name')->paginate(50);

        // past_orders tablosundan harcama toplamını hesapla
        $accounts->getCollection()->transform(function ($account) {
            $spending = (float) PastOrder::where('cafe_id', $account->cafe_id)
                ->where('cari_account_id', $account->id)
                ->sum('total_amount');

            $balance = (float) ($account->current_balance ?? 0);
            $payments = $spending + $balance;
            if ($payments < 0) {
                $payments = 0.0;
            }

            $account->total_spending = $spending;
            $account->total_payments = $payments;

            return $account;
        });

        return response()->json($accounts);
    }

    /**
     * Tek cari hesap detayı
     */
    public function show(int $id): JsonResponse
    {
        $account = CariAccount::findOrFail($id);

        $spending = (float) PastOrder::where('cafe_id', $account->cafe_id)
            ->where('cari_account_id', $account->id)
            ->sum('total_amount');

        $balance = (float) $account->current_balance;
        $payments = $spending + $balance;
        if ($payments < 0) {
            $payments = 0.0;
        }

        $account->total_spending = $spending;
        $account->total_payments = $payments;

        return response()->json([
            'success' => true,
            'data'    => $account,
        ]);
    }

    /**
     * Yeni cari hesap oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cafe_id'       => 'required|integer',
            'name'          => 'required|string|max:255',
            'customer_type' => 'required|string|max:50',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:255',
            'gender'        => 'nullable|string|max:20',
            'birthday'      => 'nullable|date',
            'credit_limit'  => 'nullable|numeric|min:0',
            'is_active'     => 'nullable|boolean',
        ]);

        $account = CariAccount::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $account,
            'message' => 'Cari hesap oluşturuldu',
        ], 201);
    }

    /**
     * Cari hesap güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $account = CariAccount::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'nullable|string|max:255',
            'customer_type' => 'nullable|string|max:50',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:255',
            'gender'        => 'nullable|string|max:20',
            'birthday'      => 'nullable|date',
            'credit_limit'  => 'nullable|numeric|min:0',
            'is_active'     => 'nullable|boolean',
        ]);

        $account->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $account,
            'message' => 'Cari hesap güncellendi',
        ]);
    }

    /**
     * Cari hesap sil
     */
    public function destroy(int $id): JsonResponse
    {
        $account = CariAccount::findOrFail($id);
        $account->delete();

        return response()->json([
            'success' => true,
            'deleted' => true,
            'message' => 'Cari hesap silindi',
        ]);
    }

    /**
     * Bakiye ekle (ödeme al)
     */
    public function addBalance(Request $request, int $id): JsonResponse
    {
        $request->validate(['amount' => 'required|numeric']);

        $account = CariAccount::findOrFail($id);
        $account->addBalance($request->amount);

        return response()->json([
            'success' => true,
            'data'    => $account,
            'message' => 'Bakiye eklendi',
        ]);
    }

    /**
     * Bakiye düş
     */
    public function deductBalance(Request $request, int $id): JsonResponse
    {
        $request->validate(['amount' => 'required|numeric']);

        $account = CariAccount::findOrFail($id);
        $account->deductBalance($request->amount);

        return response()->json([
            'success' => true,
            'data'    => $account,
            'message' => 'Bakiye düşüldü',
        ]);
    }
}
