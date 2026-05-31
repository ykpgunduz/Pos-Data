<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PastItem;
use App\Models\PastOrder;
use App\Jobs\AggregatePastOrdersJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PastOrderController extends Controller
{
    /**
     * Ana backend'den gelen sipariş + ürün verilerini toplu kaydet.
     *
     * POST /api/past-orders
     * Body: { cafe_id, order_number, table_number, ..., items: [{product_id, product_name, quantity, price}, ...] }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cafe_id'          => 'required|integer',
            'order_number'     => 'required|string|max:50',
            'table_number'     => 'nullable|integer',
            'cari_account_id'  => 'nullable|integer',
            'customer'         => 'nullable|integer',
            'customer_male'    => 'nullable|integer|min:0',
            'customer_female'  => 'nullable|integer|min:0',
            'customer_child'   => 'nullable|integer|min:0',
            'total_amount'     => 'nullable|integer',
            'net_amount'       => 'nullable|integer',
            'cash'             => 'nullable|integer',
            'card'             => 'nullable|integer',
            'iban'             => 'nullable|string',
            'treat'            => 'nullable|integer',
            'discount'         => 'nullable|integer',
            'self_treat'       => 'nullable|string',
            'closed_by'        => 'nullable|string|max:100',
            'opened_by_name'   => 'nullable|string|max:100',
            'closed_by_name'   => 'nullable|string|max:100',
            // Ürün detayları
            'items'            => 'nullable|array',
            'items.*.product_id'   => 'nullable|integer',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.quantity'     => 'nullable|integer|min:1',
            'items.*.price'        => 'nullable|numeric',
            'items.*.cost'         => 'nullable|numeric',
            'items.*.tax_rate'     => 'nullable|numeric',
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                // Sipariş kaydet (duplicate order_number'ı engelle)
                $orderData = collect($validated)->except('items')->toArray();
                $orderData['customer_male']   = $orderData['customer_male'] ?? 0;
                $orderData['customer_female'] = $orderData['customer_female'] ?? 0;
                $orderData['customer_child']  = $orderData['customer_child'] ?? 0;

                $pastOrder = PastOrder::updateOrCreate(
                    [
                        'cafe_id'      => $orderData['cafe_id'],
                        'order_number' => $orderData['order_number'],
                    ],
                    $orderData
                );

                // Ürünleri kaydet
                if (!empty($validated['items'])) {
                    foreach ($validated['items'] as $item) {
                        PastItem::create([
                            'cafe_id'      => $validated['cafe_id'],
                            'order_number' => $validated['order_number'],
                            'product_id'   => $item['product_id'] ?? null,
                            'product_name' => $item['product_name'] ?? null,
                            'quantity'     => $item['quantity'] ?? 1,
                            'price'        => $item['price'] ?? 0,
                            'cost'         => $item['cost'] ?? 0,
                            'tax_rate'     => $item['tax_rate'] ?? 0,
                        ]);
                    }
                }

                return $pastOrder;
            });

            // Raporları anında güncelle (Bugün için)
            AggregatePastOrdersJob::dispatchSync(now()->toDateString(), $validated['cafe_id']);

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'Sipariş ve ürünler başarıyla kaydedildi.',
            ], 201);
        } catch (\Throwable $e) {
            Log::error('PastOrder store hatası: ' . $e->getMessage(), [
                'order_number' => $validated['order_number'] ?? 'unknown',
                'trace'        => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Veri kaydedilemedi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tek sipariş detayı
     *
     * GET /api/past-orders/{orderNumber}?cafe_id=X
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate(['cafe_id' => 'required|integer']);

        $order = PastOrder::where('cafe_id', $request->cafe_id)
            ->where('order_number', $orderNumber)
            ->with(['items' => function ($q) use ($request) {
                $q->where('cafe_id', $request->cafe_id);
            }])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Sipariş bulunamadı.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    /**
     * Geçmiş Siparişi Düzenle (Sadece ödeme tipleri ve misafir sayısı)
     *
     * PUT /api/past-orders/{orderNumber}
     */
    public function update(Request $request, string $orderNumber): JsonResponse
    {
        $validated = $request->validate([
            'cafe_id'          => 'required|integer',
            'customer_male'    => 'nullable|integer|min:0',
            'customer_female'  => 'nullable|integer|min:0',
            'customer_child'   => 'nullable|integer|min:0',
            'cash'             => 'nullable|integer',
            'card'             => 'nullable|integer',
            'iban'             => 'nullable|string',
            'treat'            => 'nullable|integer',
            'discount'         => 'nullable|integer',
        ]);

        $order = PastOrder::where('cafe_id', $validated['cafe_id'])
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Sipariş bulunamadı.',
            ], 404);
        }

        try {
            DB::transaction(function () use ($order, $validated) {
                if (isset($validated['customer_male'])) $order->customer_male = $validated['customer_male'];
                if (isset($validated['customer_female'])) $order->customer_female = $validated['customer_female'];
                if (isset($validated['customer_child'])) $order->customer_child = $validated['customer_child'];
                
                $order->customer = $order->customer_male + $order->customer_female + $order->customer_child;

                if (array_key_exists('cash', $validated)) $order->cash = $validated['cash'];
                if (array_key_exists('card', $validated)) $order->card = $validated['card'];
                if (array_key_exists('iban', $validated)) $order->iban = $validated['iban'];
                if (array_key_exists('treat', $validated)) $order->treat = $validated['treat'];
                if (array_key_exists('discount', $validated)) $order->discount = $validated['discount'];

                $order->save();
            });

            // Raporları güncel tarih için tekrar hesapla (Siparişin asıl tarihi)
            $orderDate = $order->created_at->toDateString();
            AggregatePastOrdersJob::dispatchSync($orderDate, $validated['cafe_id']);

            return response()->json([
                'success' => true,
                'data'    => $order,
                'message' => 'Sipariş başarıyla güncellendi.',
            ]);
        } catch (\Throwable $e) {
            Log::error('PastOrder update hatası: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Sipariş güncellenemedi.',
            ], 500);
        }
    }

    /**
     * Adisyon Raporu
     *
     * GET /api/past-orders/report
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate(['cafe_id' => 'required|integer']);

        $query = PastOrder::where('cafe_id', $request->cafe_id)
            ->with(['items' => function ($q) use ($request) {
                $q->where('cafe_id', $request->cafe_id);
            }]);

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Klon oluşturarak özet hesapla
        $summaryQuery = clone $query;
        $summaryData = clone $query;
        
        $totals = $summaryQuery->select(
            DB::raw('COUNT(id) as total_orders'),
            DB::raw('SUM(customer_male + customer_female + customer_child) as total_customers'),
            DB::raw('SUM(customer_male) as total_customer_male'),
            DB::raw('SUM(customer_female) as total_customer_female'),
            DB::raw('SUM(customer_child) as total_customer_child'),
            DB::raw('SUM(cash) as total_cash'),
            DB::raw('SUM(card) as total_card'),
            DB::raw('SUM(CAST(iban AS NUMERIC)) as total_iban'),
            DB::raw('SUM(treat) as total_treat'),
            DB::raw('SUM(total_amount) as total_gross'),
            DB::raw('SUM(net_amount) as total_net')
        )->first();

        // Cari hesapları hesapla (şu anki query'de cari alanı yok, eğer eklerseniz toplayın. Burada DB tablosu bazında sum)
        $totalCari = $summaryData->whereNotNull('cari_account_id')->sum('total_amount');

        $summary = [
            'total_orders'          => (int) ($totals->total_orders ?? 0),
            'total_customers'       => (int) ($totals->total_customers ?? 0),
            'total_customer_male'   => (int) ($totals->total_customer_male ?? 0),
            'total_customer_female' => (int) ($totals->total_customer_female ?? 0),
            'total_customer_child'  => (int) ($totals->total_customer_child ?? 0),
            'total_cash'            => (int) ($totals->total_cash ?? 0),
            'total_card'            => (int) ($totals->total_card ?? 0),
            'total_iban'            => (int) ($totals->total_iban ?? 0),
            'total_cari'            => (int) $totalCari,
            'total_treat'           => (int) ($totals->total_treat ?? 0),
            'total_gross'           => (int) ($totals->total_gross ?? 0),
            'total_net'             => (int) ($totals->total_net ?? 0),
        ];

        // Period hesapla (Dinamik)
        $periodsQuery = clone $query;
        $periodsResult = $periodsQuery->select(
            DB::raw("SUM(CASE WHEN EXTRACT(HOUR FROM created_at) >= 10 AND EXTRACT(HOUR FROM created_at) < 14 THEN total_amount ELSE 0 END) as morning_total"),
            DB::raw("SUM(CASE WHEN EXTRACT(HOUR FROM created_at) >= 10 AND EXTRACT(HOUR FROM created_at) < 14 THEN (customer_male + customer_female + customer_child) ELSE 0 END) as morning_customers"),
            DB::raw("SUM(CASE WHEN EXTRACT(HOUR FROM created_at) >= 14 AND EXTRACT(HOUR FROM created_at) < 18 THEN total_amount ELSE 0 END) as afternoon_total"),
            DB::raw("SUM(CASE WHEN EXTRACT(HOUR FROM created_at) >= 14 AND EXTRACT(HOUR FROM created_at) < 18 THEN (customer_male + customer_female + customer_child) ELSE 0 END) as afternoon_customers"),
            DB::raw("SUM(CASE WHEN EXTRACT(HOUR FROM created_at) >= 18 OR EXTRACT(HOUR FROM created_at) < 10 THEN total_amount ELSE 0 END) as evening_total"),
            DB::raw("SUM(CASE WHEN EXTRACT(HOUR FROM created_at) >= 18 OR EXTRACT(HOUR FROM created_at) < 10 THEN (customer_male + customer_female + customer_child) ELSE 0 END) as evening_customers")
        )->first();

        $morningTotal = (int) ($periodsResult->morning_total ?? 0);
        $morningCustomers = (int) ($periodsResult->morning_customers ?? 0);
        $afternoonTotal = (int) ($periodsResult->afternoon_total ?? 0);
        $afternoonCustomers = (int) ($periodsResult->afternoon_customers ?? 0);
        $eveningTotal = (int) ($periodsResult->evening_total ?? 0);
        $eveningCustomers = (int) ($periodsResult->evening_customers ?? 0);

        $periods = [
            'morning'   => [
                'total' => $morningTotal, 
                'customers' => $morningCustomers, 
                'per_person' => $morningCustomers > 0 ? round($morningTotal / $morningCustomers, 2) : 0
            ],
            'afternoon' => [
                'total' => $afternoonTotal, 
                'customers' => $afternoonCustomers, 
                'per_person' => $afternoonCustomers > 0 ? round($afternoonTotal / $afternoonCustomers, 2) : 0
            ],
            'evening'   => [
                'total' => $eveningTotal, 
                'customers' => $eveningCustomers, 
                'per_person' => $eveningCustomers > 0 ? round($eveningTotal / $eveningCustomers, 2) : 0
            ],
        ];

        // Sayfalama
        $perPage = $request->input('per_page', 60);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'periods' => $periods,
                'orders'  => $orders,
                'summary' => $summary,
            ],
        ]);
    }
}
