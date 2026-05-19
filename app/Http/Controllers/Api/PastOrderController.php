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
            // Ürün detayları
            'items'            => 'nullable|array',
            'items.*.product_id'   => 'nullable|integer',
            'items.*.product_name' => 'nullable|string|max:255',
            'items.*.quantity'     => 'nullable|integer|min:1',
            'items.*.price'        => 'nullable|integer',
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
                        ]);
                    }
                }

                return $pastOrder;
            });

            // Raporları anında güncelle (Bugün için)
            AggregatePastOrdersJob::dispatch(now()->toDateString(), $validated['cafe_id']);

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
            ->with('items')
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
}
