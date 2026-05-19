<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySalesSummary;
use App\Models\ProductSalesSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Günlük Satış Raporu
     * GET /api/reports/daily-sales?cafe_id=X&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     *
     * Hazır özet tablolardan çeker — 1 yıllık sorgu bile sadece ~365 satırdır.
     */
    public function dailySales(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'date'       => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? ($request->date ?? now()->toDateString());
        $endDate   = $request->end_date   ?? ($request->date ?? now()->toDateString());

        $summaries = DailySalesSummary::where('cafe_id', $cafeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        // Toplamları hesapla
        $totalSales     = $summaries->sum('total_turnover');
        $totalOrders    = $summaries->sum('total_orders');
        $totalCustomers = $summaries->sum('total_customers');
        $totalCash      = $summaries->sum('total_cash');
        $totalCard      = $summaries->sum('total_card');
        $totalIban      = $summaries->sum('total_iban');
        $totalTreat     = $summaries->sum('total_treat');
        $totalDiscount  = $summaries->sum('total_discount');
        $totalNet       = $summaries->sum('total_net_amount');
        $totalTax       = $summaries->sum('total_tax_amount');
        $totalMale      = $summaries->sum('total_customer_male');
        $totalFemale    = $summaries->sum('total_customer_female');
        $totalChild     = $summaries->sum('total_customer_child');

        // Ödeme türleri
        $paymentTypes = [
            ['type' => 'Nakit', 'amount' => $totalCash],
            ['type' => 'Kart',  'amount' => $totalCard],
            ['type' => 'IBAN',  'amount' => $totalIban],
        ];

        // Günlük zaman serisi (chart verisi)
        $dailySeries = $summaries->map(fn ($s) => [
            'date'   => $s->date->format('Y-m-d'),
            'amount' => $s->total_turnover,
            'orders' => $s->total_orders,
        ])->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_sales'         => $totalSales,
                'total_orders'        => $totalOrders,
                'average_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
                'total_customers'     => $totalCustomers,
                'customer_male'       => $totalMale,
                'customer_female'     => $totalFemale,
                'customer_child'      => $totalChild,
                'total_net'           => $totalNet,
                'total_tax'           => $totalTax,
                'total_treat'         => $totalTreat,
                'total_discount'      => $totalDiscount,
                'payment_types'       => $paymentTypes,
                'daily_series'        => $dailySeries,
            ],
        ]);
    }

    /**
     * Ödeme Tipi Raporu
     * GET /api/reports/payment-types?cafe_id=X&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function paymentTypes(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        $summaries = DailySalesSummary::where('cafe_id', $cafeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_cash'     => $summaries->sum('total_cash'),
                'total_card'     => $summaries->sum('total_card'),
                'total_iban'     => $summaries->sum('total_iban'),
                'total_treat'    => $summaries->sum('total_treat'),
                'total_discount' => $summaries->sum('total_discount'),
                'total_turnover' => $summaries->sum('total_turnover'),
            ],
        ]);
    }

    /**
     * KDV ve Vergi Raporu
     * GET /api/reports/tax-report?cafe_id=X&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function taxReport(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        $summaries = DailySalesSummary::where('cafe_id', $cafeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalGross = $summaries->sum('total_turnover');
        $totalTax   = $summaries->sum('total_tax_amount');
        $totalNet   = $summaries->sum('total_net_amount');

        return response()->json([
            'success' => true,
            'data'    => [
                'total_gross'  => $totalGross,
                'total_tax'    => $totalTax,
                'total_net'    => $totalNet,
            ],
        ]);
    }

    /**
     * En Çok Satan Ürünler Raporu
     * GET /api/reports/product-sales?cafe_id=X&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function productSales(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        $products = ProductSalesSummary::where('cafe_id', $cafeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('product_id, product_name, SUM(quantity_sold) as total_quantity, SUM(total_revenue) as total_amount')
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_amount')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    /**
     * İkram ve İndirim Raporu
     * GET /api/reports/complimentary-discount?cafe_id=X&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
     */
    public function complimentaryDiscount(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        $summaries = DailySalesSummary::where('cafe_id', $cafeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_treat'    => $summaries->sum('total_treat'),
                'total_discount' => $summaries->sum('total_discount'),
                'daily' => $summaries->map(fn ($s) => [
                    'date'     => $s->date->format('Y-m-d'),
                    'treat'    => $s->total_treat,
                    'discount' => $s->total_discount,
                ])->values(),
            ],
        ]);
    }

    /**
     * Veri bulunan dönemleri döndür (yıl-ay bazında)
     * GET /api/reports/available-periods?cafe_id=X
     */
    public function availablePeriods(Request $request): JsonResponse
    {
        $request->validate(['cafe_id' => 'required|integer']);

        $cafeId = $request->cafe_id;

        $periods = DailySalesSummary::where('cafe_id', $cafeId)
            ->selectRaw('EXTRACT(YEAR FROM date) as year, EXTRACT(MONTH FROM date) as month, SUM(total_orders) as order_count')
            ->groupByRaw('EXTRACT(YEAR FROM date), EXTRACT(MONTH FROM date)')
            ->orderByRaw('EXTRACT(YEAR FROM date) DESC, EXTRACT(MONTH FROM date) DESC')
            ->get();

        $years = $periods->pluck('year')->unique()->sort()->reverse()->values();
        $months = $periods->map(fn ($p) => [
            'year'        => (int) $p->year,
            'month'       => (int) $p->month,
            'order_count' => (int) $p->order_count,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'years'  => $years,
                'months' => $months,
            ],
        ]);
    }
}
