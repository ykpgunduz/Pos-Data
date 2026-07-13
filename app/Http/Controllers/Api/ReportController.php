<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySalesSummary;
use App\Models\PastItem;
use App\Models\PastOrder;
use App\Models\ProductSalesSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $totalCost      = $summaries->sum('total_cost');

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

        // Kar = Net Ciro (KDV hariç) - Toplam Gider
        $totalProfit = $totalNet - $totalCost;

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
                'total_expense'       => $totalCost,
                'total_profit'        => $totalProfit,
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
     *
     * past_items tablosundan her ürünün tax_rate alanına göre dinamik KDV kırılımı üretir.
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

        // past_items tablosundan tax_rate bazında gruplama
        // price alanı KDV dahil brüt fiyattır, quantity ile çarpılır
        $brackets = PastItem::where('past_items.cafe_id', $cafeId)
            ->join('past_orders', function ($join) use ($cafeId) {
                $join->on('past_items.order_number', '=', 'past_orders.order_number')
                     ->where('past_orders.cafe_id', '=', $cafeId);
            })
            ->whereBetween('past_orders.created_at', [$startDate, $endDate])
            ->select(
                'past_items.tax_rate',
                DB::raw('SUM(past_items.quantity * past_items.price) as gross_sales')
            )
            ->groupBy('past_items.tax_rate')
            ->orderBy('past_items.tax_rate')
            ->get();

        $colors = [
            'hsl(199, 89%, 48%)',   // sky
            'hsl(217, 91%, 60%)',   // blue
            'hsl(262, 83%, 58%)',   // violet
            'hsl(280, 67%, 55%)',   // purple
            'hsl(339, 70%, 55%)',   // rose
            'hsl(25, 95%, 53%)',    // orange
            'hsl(142, 71%, 45%)',   // green
        ];

        $data = $brackets->values()->map(function ($row, $index) use ($colors) {
            $rate       = (float) $row->tax_rate;
            $grossSales = (float) $row->gross_sales;

            // Brüt satıştan (KDV dahil fiyat) matrah ve KDV'yi ayır
            // Matrah = Brüt / (1 + oran/100)
            $netSales  = $rate > 0 ? round($grossSales / (1 + $rate / 100), 2) : $grossSales;
            $taxAmount = round($grossSales - $netSales, 2);

            return [
                'bracket'    => '%' . intval($rate) . ' KDV',
                'rate'       => $rate,
                'netSales'   => $netSales,
                'taxAmount'  => $taxAmount,
                'grossSales' => $grossSales,
                'color'      => $colors[$index % count($colors)] ?? 'hsl(0, 0%, 50%)',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
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
            ->orderByDesc('total_quantity')
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

    /**
     * Departman / Kategori Bazlı Satış Raporu
     * past_orders tablosundan Masa vs Paket/Hızlı ayrımı yapar.
     */
    public function departmentSales(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        // Masa siparişleri: table_number NOT NULL
        // Paket/Hızlı: table_number IS NULL veya order_number QS- ile başlayan
        $orders = PastOrder::where('cafe_id', $cafeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("CASE WHEN order_number LIKE 'QS-%' OR table_number IS NULL THEN 'Paket/Hızlı' ELSE 'Masa' END as department"),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_amount')
            )
            ->groupBy('department')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    /**
     * Karlılık Raporu
     * past_items tablosundan ürün bazlı maliyet, ciro, kar ve marj hesaplar.
     */
    public function profitability(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        $products = PastItem::where('past_items.cafe_id', $cafeId)
            ->join('past_orders', function ($join) use ($cafeId) {
                $join->on('past_items.order_number', '=', 'past_orders.order_number')
                     ->where('past_orders.cafe_id', '=', $cafeId);
            })
            ->whereBetween('past_orders.created_at', [$startDate, $endDate])
            ->select(
                'past_items.product_id',
                'past_items.product_name',
                DB::raw('SUM(past_items.quantity) as total_quantity'),
                DB::raw('SUM(past_items.quantity * past_items.price) as total_amount'),
                DB::raw('SUM(past_items.quantity * past_items.cost) as totalCost')
            )
            ->groupBy('past_items.product_id', 'past_items.product_name')
            ->orderByDesc('total_amount')
            ->get();

        $data = $products->map(function ($p) {
            $revenue  = (float) $p->total_amount;
            $cost     = (float) $p->totalCost;
            $profit   = $revenue - $cost;
            $margin   = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;
            $quantity = (int) $p->total_quantity;
            $unitCost = $quantity > 0 ? round($cost / $quantity, 2) : 0;

            return [
                'product_id'     => $p->product_id,
                'product_name'   => $p->product_name,
                'total_quantity' => $quantity,
                'total_amount'   => $revenue,
                'totalCost'      => $cost,
                'profit'         => $profit,
                'margin'         => $margin,
                'unitCost'       => $unitCost,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Satılmayan Ürünler Raporu
     * Seçilen dönemde hiç satışı olmayan ürünleri listeler.
     * Ana backend'den ürün listesini almak yerine, past_items'da olan ürünleri bulup
     * dışarda kalanları döndürür.
     */
    public function unsoldProducts(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        // Dönem içinde satılan ürün ID'leri
        $soldIds = PastItem::where('past_items.cafe_id', $cafeId)
            ->join('past_orders', function ($join) use ($cafeId) {
                $join->on('past_items.order_number', '=', 'past_orders.order_number')
                     ->where('past_orders.cafe_id', '=', $cafeId);
            })
            ->whereBetween('past_orders.created_at', [$startDate, $endDate])
            ->distinct()
            ->pluck('past_items.product_id')
            ->filter()
            ->toArray();

        // Tüm dönemde bilinen ürünler (en az bir kez satılmış)
        $allProducts = PastItem::where('cafe_id', $cafeId)
            ->select('product_id', 'product_name')
            ->distinct()
            ->get();

        $unsold = $allProducts
            ->filter(fn ($p) => !in_array($p->product_id, $soldIds))
            ->map(fn ($p) => [
                'product_id'   => $p->product_id,
                'product_name' => $p->product_name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $unsold,
        ]);
    }

    /**
     * Masa Doluluk Raporu
     * past_orders tablosundan masa bazlı sipariş sayısı ve ciro gösterir.
     */
    public function tableOccupancy(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        $tables = PastOrder::where('cafe_id', $cafeId)
            ->whereNotNull('table_number')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'table_number',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('SUM(customer_male + customer_female + customer_child) as total_customers')
            )
            ->groupBy('table_number')
            ->orderByDesc('total_amount')
            ->get();

        $data = $tables->map(fn ($t) => [
            'table_number'    => $t->table_number,
            'table_name'      => 'Masa ' . $t->table_number,
            'total_orders'    => (int) $t->total_orders,
            'total_amount'    => (float) $t->total_amount,
            'total_customers' => (int) $t->total_customers,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function staffPerformance(Request $request): JsonResponse
    {
        $request->validate(['cafe_id' => 'required|integer', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date']);
        $cafeId       = $request->cafe_id;
        $startDateVal = $request->start_date ? $request->start_date . ' 00:00:00' : now()->startOfMonth()->toDateTimeString();
        $endDateVal   = $request->end_date   ? $request->end_date . ' 23:59:59'   : now()->endOfDay()->toDateTimeString();

        $staffs = PastOrder::where('cafe_id', $cafeId)
            ->whereBetween('created_at', [$startDateVal, $endDateVal])
            ->whereNotNull('closed_by')
            ->selectRaw('closed_by as user_name, SUM(total_amount) as total_sales, COUNT(*) as orders_handled')
            ->groupBy('closed_by')
            ->get();

        $data = $staffs->map(function ($s, $i) {
            return [
                'user_id' => $i + 1,
                'user_name' => $s->user_name,
                'entry_time' => '08:00',
                'exit_time' => '17:00',
                'work_hours' => 9,
                'total_sales' => (float)$s->total_sales,
                'orders_handled' => $s->orders_handled
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function wasteReport(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $cafeId    = $request->cafe_id;
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate   = $request->end_date   ?? now()->toDateString();

        $wastages = \App\Models\Wastage::where('cafe_id', $cafeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'desc')
            ->get();

        $totalCost = $wastages->sum('cost');

        return response()->json([
            'success' => true,
            'data'    => [
                'items'      => $wastages,
                'total_cost' => $totalCost,
            ]
        ]);
    }

    public function customerReport(Request $request): JsonResponse
    {
        $request->validate([
            'cafe_id'    => 'required|integer',
            'start_date' => 'nullable|string',
            'end_date'   => 'nullable|string',
        ]);

        $cafeId = $request->cafe_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query = PastOrder::where('cafe_id', $cafeId);

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Summary stats
        $stats = (clone $query)->select(
            DB::raw('SUM(customer_male + customer_female + customer_child) as total_customers'),
            DB::raw('SUM(customer_male) as total_male'),
            DB::raw('SUM(customer_female) as total_female'),
            DB::raw('SUM(customer_child) as total_child'),
            DB::raw('COUNT(id) as total_orders'),
            DB::raw('SUM(net_amount) as total_revenue')
        )->first();

        $totalCustomers = (int) ($stats->total_customers ?? 0);
        $totalMale = (int) ($stats->total_male ?? 0);
        $totalFemale = (int) ($stats->total_female ?? 0);
        $totalChild = (int) ($stats->total_child ?? 0);
        $totalOrders = (int) ($stats->total_orders ?? 0);
        $totalRevenue = (float) ($stats->total_revenue ?? 0);

        $avgSpend = $totalCustomers > 0 ? round($totalRevenue / $totalCustomers, 2) : 0;
        $avgCustPerOrder = $totalOrders > 0 ? round($totalCustomers / $totalOrders, 1) : 0;

        // Gender breakdown
        $genderBreakdown = [];
        if ($totalCustomers > 0) {
            $genderBreakdown[] = [
                'type' => 'Erkek',
                'count' => $totalMale,
                'percentage' => round(($totalMale / $totalCustomers) * 100, 1)
            ];
            $genderBreakdown[] = [
                'type' => 'Kadın',
                'count' => $totalFemale,
                'percentage' => round(($totalFemale / $totalCustomers) * 100, 1)
            ];
            $genderBreakdown[] = [
                'type' => 'Çocuk',
                'count' => $totalChild,
                'percentage' => round(($totalChild / $totalCustomers) * 100, 1)
            ];
        }

        // Hourly distribution
        $hourlyData = (clone $query)
            ->select(
                DB::raw('EXTRACT(HOUR FROM created_at) as hr'),
                DB::raw('SUM(customer_male + customer_female + customer_child) as count')
            )
            ->groupBy('hr')
            ->orderBy('hr')
            ->get();

        $hourlyMap = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyMap[$i] = 0;
        }
        foreach ($hourlyData as $row) {
            $hourlyMap[(int) $row->hr] = (int) $row->count;
        }
        $hourlyDistribution = [];
        foreach ($hourlyMap as $hour => $count) {
            $hourlyDistribution[] = ['hour' => $hour, 'count' => $count];
        }

        // Daily trend
        $dailyData = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as dt'),
                DB::raw('SUM(customer_male + customer_female + customer_child) as customers'),
                DB::raw('SUM(net_amount) as revenue')
            )
            ->groupBy('dt')
            ->orderBy('dt')
            ->get();

        $dailyTrend = $dailyData->map(fn($row) => [
            'date' => $row->dt,
            'customers' => (int) $row->customers,
            'revenue' => (float) $row->revenue
        ])->values()->all();

        // Top spending orders
        $topSpending = (clone $query)
            ->where('net_amount', '>', 0)
            ->orderBy('net_amount', 'desc')
            ->limit(5)
            ->get();

        $topSpendingOrders = $topSpending->map(fn($o) => [
            'order_number' => $o->order_number,
            'total_amount' => $o->net_amount,
            'customer_count' => ($o->customer_male + $o->customer_female + $o->customer_child) ?: 1,
            'date' => $o->created_at->toDateTimeString()
        ])->values()->all();

        // Visit frequency (based on guest count size per order)
        $groupStats = (clone $query)->select(
            DB::raw('SUM(CASE WHEN (customer_male + customer_female + customer_child) = 1 THEN 1 ELSE 0 END) as single_count'),
            DB::raw('SUM(CASE WHEN (customer_male + customer_female + customer_child) = 2 THEN 1 ELSE 0 END) as double_count'),
            DB::raw('SUM(CASE WHEN (customer_male + customer_female + customer_child) BETWEEN 3 AND 4 THEN 1 ELSE 0 END) as small_group_count'),
            DB::raw('SUM(CASE WHEN (customer_male + customer_female + customer_child) >= 5 THEN 1 ELSE 0 END) as group_count')
        )->first();

        $single = (int) ($groupStats->single_count ?? 0);
        $double = (int) ($groupStats->double_count ?? 0);
        $smallGroup = (int) ($groupStats->small_group_count ?? 0);
        $group = (int) ($groupStats->group_count ?? 0);
        $totalFreq = $single + $double + $smallGroup + $group;

        $visitFrequency = [
            [
                'range' => 'Tek Başına',
                'count' => $single,
                'percentage' => $totalFreq > 0 ? round(($single / $totalFreq) * 100, 1) : 0
            ],
            [
                'range' => 'İki Kişi',
                'count' => $double,
                'percentage' => $totalFreq > 0 ? round(($double / $totalFreq) * 100, 1) : 0
            ],
            [
                'range' => 'Küçük Grup (3-4)',
                'count' => $smallGroup,
                'percentage' => $totalFreq > 0 ? round(($smallGroup / $totalFreq) * 100, 1) : 0
            ],
            [
                'range' => 'Grup (5+)',
                'count' => $group,
                'percentage' => $totalFreq > 0 ? round(($group / $totalFreq) * 100, 1) : 0
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $totalCustomers,
                'total_male' => $totalMale,
                'total_female' => $totalFemale,
                'total_child' => $totalChild,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'avg_spend_per_customer' => $avgSpend,
                'avg_customers_per_order' => $avgCustPerOrder,
                'hourly_distribution' => $hourlyDistribution,
                'daily_trend' => $dailyTrend,
                'gender_breakdown' => $genderBreakdown,
                'top_spending_orders' => $topSpendingOrders,
                'visit_frequency' => $visitFrequency
            ]
        ]);
    }
}
