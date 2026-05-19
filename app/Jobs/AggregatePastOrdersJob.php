<?php

namespace App\Jobs;

use App\Models\DailySalesSummary;
use App\Models\PastItem;
use App\Models\PastOrder;
use App\Models\ProductSalesSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Günlük ham verileri okuyup özet tablolara (upsert) yazan job.
 *
 * Çalışma zamanı:
 *  - Her gece 05:00'te schedule ile
 *  - Veya gün sonu tetikleme ile (PastOrderController store sonrası opsiyonel)
 *
 * Büyük veri setlerinde tıkanmayı önlemek için chunk kullanır.
 */
class AggregatePastOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 dakika

    public function __construct(
        private readonly ?string $targetDate = null,
        private readonly ?int $cafeId = null,
    ) {}

    public function handle(): void
    {
        $date = $this->targetDate ?? now()->subDay()->toDateString();

        Log::info("AggregatePastOrdersJob başlatıldı", [
            'date'    => $date,
            'cafe_id' => $this->cafeId ?? 'tümü',
        ]);

        try {
            $this->aggregateDailySales($date);
            $this->aggregateProductSales($date);

            Log::info("AggregatePastOrdersJob tamamlandı", ['date' => $date]);
        } catch (\Throwable $e) {
            Log::error("AggregatePastOrdersJob hatası: " . $e->getMessage(), [
                'date'  => $date,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Queue retry mekanizması devreye girsin
        }
    }

    /**
     * Günlük satış özetini hesapla ve upsert et.
     */
    private function aggregateDailySales(string $date): void
    {
        $query = PastOrder::whereDate('created_at', $date);

        if ($this->cafeId) {
            $query->where('cafe_id', $this->cafeId);
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $ibanSql = $isPgsql
            ? "SUM(CASE WHEN iban ~ '^[0-9]+$' AND cari_account_id IS NULL THEN CAST(iban AS BIGINT) ELSE 0 END) as total_iban"
            : "SUM(CASE WHEN iban REGEXP '^[0-9]+$' AND cari_account_id IS NULL THEN CAST(iban AS UNSIGNED) ELSE 0 END) as total_iban";

        // Cafe bazında gruplayıp chunk ile işle
        $query->select(
            'cafe_id',
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(total_amount) as total_turnover'),
            DB::raw('SUM(net_amount) as total_net_amount'),
            DB::raw('SUM(COALESCE(cash, 0)) as total_cash'),
            DB::raw('SUM(COALESCE(card, 0)) as total_card'),
            DB::raw($ibanSql),
            DB::raw('SUM(COALESCE(treat, 0)) as total_treat'),
            DB::raw('SUM(COALESCE(discount, 0)) as total_discount'),
            DB::raw('SUM(COALESCE(customer, 0)) as total_customers'),
            DB::raw('SUM(customer_male) as total_customer_male'),
            DB::raw('SUM(customer_female) as total_customer_female'),
            DB::raw('SUM(customer_child) as total_customer_child'),
        )
        ->groupBy('cafe_id')
        ->orderBy('cafe_id')
        ->chunk(100, function ($rows) use ($date) {
            foreach ($rows as $row) {
                // Vergi hesabı: Brüt - Net = Vergi (yaklaşık)
                $taxAmount = max(0, (int) $row->total_turnover - (int) $row->total_net_amount);

                // Toplam maliyeti bul (cost * quantity)
                $totalCost = \App\Models\PastItem::where('cafe_id', $row->cafe_id)
                    ->whereDate('created_at', $date)
                    ->select(DB::raw('SUM(cost * quantity) as total_cost'))
                    ->value('total_cost') ?? 0;

                DailySalesSummary::updateOrCreate(
                    [
                        'cafe_id' => $row->cafe_id,
                        'date'    => $date,
                    ],
                    [
                        'total_turnover'       => (int) $row->total_turnover,
                        'total_cost'           => (int) $totalCost,
                        'total_orders'         => (int) $row->total_orders,
                        'total_net_amount'     => (int) $row->total_net_amount,
                        'total_tax_amount'     => $taxAmount,
                        'total_cash'           => (int) $row->total_cash,
                        'total_card'           => (int) $row->total_card,
                        'total_iban'           => (int) $row->total_iban,
                        'total_treat'          => (int) $row->total_treat,
                        'total_discount'       => (int) $row->total_discount,
                        'total_customers'      => (int) $row->total_customers,
                        'total_customer_male'  => (int) $row->total_customer_male,
                        'total_customer_female'=> (int) $row->total_customer_female,
                        'total_customer_child' => (int) $row->total_customer_child,
                    ]
                );
            }
        });
    }

    /**
     * Ürün bazlı satış özetini hesapla ve upsert et.
     */
    private function aggregateProductSales(string $date): void
    {
        $query = PastItem::whereDate('past_items.created_at', $date);

        if ($this->cafeId) {
            $query->where('past_items.cafe_id', $this->cafeId);
        }

        $query->select(
            'past_items.cafe_id',
            'past_items.product_id',
            'past_items.product_name',
            DB::raw('SUM(past_items.quantity) as quantity_sold'),
            DB::raw('SUM(past_items.price) as total_revenue'),
        )
        ->groupBy('past_items.cafe_id', 'past_items.product_id', 'past_items.product_name')
        ->orderBy('past_items.cafe_id')
        ->chunk(200, function ($rows) use ($date) {
            foreach ($rows as $row) {
                ProductSalesSummary::updateOrCreate(
                    [
                        'cafe_id'    => $row->cafe_id,
                        'date'       => $date,
                        'product_id' => $row->product_id,
                    ],
                    [
                        'product_name'  => $row->product_name ?? 'Bilinmeyen Ürün',
                        'quantity_sold' => (int) $row->quantity_sold,
                        'total_revenue' => (int) $row->total_revenue,
                    ]
                );
            }
        });
    }
}
