<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PastOrder;
use App\Jobs\AggregatePastOrdersJob;
use Illuminate\Support\Facades\DB;

class AggregateAllPastOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:aggregate-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregates all past orders into daily summaries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Tüm geçmiş siparişlerin özetleri oluşturuluyor...');

        $dates = PastOrder::select(DB::raw('DATE(created_at) as date'))
            ->groupBy('date')
            ->pluck('date');

        $bar = $this->output->createProgressBar(count($dates));
        $bar->start();

        foreach ($dates as $date) {
            AggregatePastOrdersJob::dispatchSync($date);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Tüm özetler başarıyla oluşturuldu!');
    }
}
