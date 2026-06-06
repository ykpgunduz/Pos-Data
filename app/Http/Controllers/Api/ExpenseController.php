<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    /**
     * Gider listesi
     * GET /expenses/list?cafe_id=X&start_date=X&end_date=X&category=X
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['cafe_id' => 'required|integer']);

        $query = Expense::where('cafe_id', $request->cafe_id)
            ->with('supplier:id,company_name');

        if ($request->start_date) {
            $query->where('expense_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('expense_date', '<=', $request->end_date);
        }
        if ($request->category && $request->category !== 'all') {
            $query->where('category', $request->category);
        }
        if ($request->has('is_recurring')) {
            $query->where('is_recurring', filter_var($request->is_recurring, FILTER_VALIDATE_BOOLEAN));
        }

        $expenses = $query->orderByDesc('expense_date')->orderByDesc('id')->get();

        return response()->json($expenses);
    }

    /**
     * Gider özet istatistikleri
     * GET /expenses/summary?cafe_id=X
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate(['cafe_id' => 'required|integer']);

        $cafeId     = $request->cafe_id;
        $today      = Carbon::today()->toDateString();
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd   = Carbon::now()->endOfMonth()->toDateString();

        $dailyTotal = Expense::where('cafe_id', $cafeId)
            ->where('expense_date', $today)
            ->sum('amount');

        $dailyCount = Expense::where('cafe_id', $cafeId)
            ->where('expense_date', $today)
            ->count();

        $monthlyTotal = Expense::where('cafe_id', $cafeId)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $recurringTotal = Expense::where('cafe_id', $cafeId)
            ->where('is_recurring', true)
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $unpaidTotal = Expense::where('cafe_id', $cafeId)
            ->where('is_paid', false)
            ->sum('amount');

        $supplierDebt = Supplier::where('cafe_id', $cafeId)
            ->where('current_balance', '<', 0)
            ->sum(DB::raw('ABS(current_balance)'));

        $supplierDebtCount = Supplier::where('cafe_id', $cafeId)
            ->where('current_balance', '<', 0)
            ->count();

        return response()->json([
            'daily_total'         => (float) $dailyTotal,
            'daily_count'         => $dailyCount,
            'monthly_total'       => (float) $monthlyTotal,
            'recurring_total'     => (float) $recurringTotal,
            'unpaid_total'        => (float) $unpaidTotal,
            'supplier_debt'       => (float) $supplierDebt,
            'supplier_debt_count' => $supplierDebtCount,
        ]);
    }

    /**
     * Kategori listesi
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'Kira', 'Elektrik', 'Su', 'Doğalgaz', 'İnternet',
            'Personel Maaşı', 'Mutfak', 'Malzeme', 'Temizlik',
            'Bakım/Onarım', 'Vergi', 'Sigorta', 'Kargo/Nakliye', 'Diğer',
        ]);
    }

    /**
     * Yeni gider ekle
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'        => 'required|integer',
            'category'       => 'required|string|max:100',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'amount'         => 'required|numeric|min:0.01',
            'expense_date'   => 'required|date',
            'is_recurring'   => 'nullable|boolean',
            'recurring_day'  => 'nullable|integer|min:1|max:31',
            'payment_method' => 'nullable|string|max:50',
            'is_paid'        => 'nullable|boolean',
            'supplier_id'    => 'nullable|integer|exists:suppliers,id',
            'added_by'       => 'nullable|integer',
        ]);

        $expense = Expense::create([
            'cafe_id'        => $request->cafe_id,
            'supplier_id'    => $request->supplier_id,
            'category'       => $request->category,
            'title'          => $request->title,
            'description'    => $request->description,
            'amount'         => $request->amount,
            'expense_date'   => $request->expense_date,
            'is_recurring'   => $request->is_recurring ?? false,
            'recurring_day'  => $request->recurring_day,
            'payment_method' => $request->payment_method ?? 'Nakit',
            'is_paid'        => $request->is_paid ?? true,
            'added_by'       => $request->added_by,
        ]);

        // Tedarikçiye bağlı gider ise borcu artır
        if ($expense->supplier_id) {
            Supplier::where('id', $expense->supplier_id)
                ->decrement('current_balance', $expense->amount);
        }

        return response()->json($expense->load('supplier:id,company_name'), 201);
    }

    /**
     * Gider güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        $request->validate([
            'category'       => 'sometimes|string|max:100',
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'amount'         => 'sometimes|numeric|min:0.01',
            'expense_date'   => 'sometimes|date',
            'is_recurring'   => 'nullable|boolean',
            'recurring_day'  => 'nullable|integer|min:1|max:31',
            'payment_method' => 'nullable|string|max:50',
            'is_paid'        => 'nullable|boolean',
            'supplier_id'    => 'nullable|integer|exists:suppliers,id',
        ]);

        $expense->update($request->only([
            'category', 'title', 'description', 'amount',
            'expense_date', 'is_recurring', 'recurring_day',
            'payment_method', 'is_paid', 'supplier_id',
        ]));

        return response()->json($expense->fresh()->load('supplier:id,company_name'));
    }

    /**
     * Gider sil
     */
    public function destroy(int $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        // Tedarikçi borcunu geri al
        if ($expense->supplier_id) {
            Supplier::where('id', $expense->supplier_id)
                ->increment('current_balance', $expense->amount);
        }

        $expense->delete();
        return response()->json(['message' => 'Gider silindi.']);
    }
}
