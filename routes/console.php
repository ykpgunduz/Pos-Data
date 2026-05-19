<?php

use App\Jobs\AggregatePastOrdersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduler
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Gece 05:00'te önceki günün verilerini özetle ──
Schedule::job(new AggregatePastOrdersJob())->dailyAt('05:00');

// ── Manuel tetikleme komutu ──
Artisan::command('data:aggregate {date?} {--cafe=}', function (?string $date = null) {
    $cafeId = $this->option('cafe') ? (int) $this->option('cafe') : null;
    $targetDate = $date ?? now()->subDay()->toDateString();

    $this->info("Aggregation başlatılıyor: {$targetDate}" . ($cafeId ? " (cafe: {$cafeId})" : ''));

    AggregatePastOrdersJob::dispatch($targetDate, $cafeId);

    $this->info('Job kuyruğa eklendi.');
})->purpose('Ham verileri özet tablolara aggregate et');
