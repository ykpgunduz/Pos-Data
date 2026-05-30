<?php

use App\Http\Controllers\Api\PastOrderController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Data Service API Routes
|--------------------------------------------------------------------------
|
| Tüm rotalar 'service.token' middleware'i ile korunur.
| Ana backend, istekleri Bearer token ile gönderir.
|
*/

Route::middleware('service.token')->group(function () {

    // ── Ham Veri Girişi ──
    Route::post('past-orders', [PastOrderController::class, 'store']);
    Route::get('past-orders/report', [PastOrderController::class, 'report']);
    Route::get('past-orders/{orderNumber}', [PastOrderController::class, 'show']);
    Route::put('past-orders/{orderNumber}', [PastOrderController::class, 'update']);

    // ── Rapor Uç Noktaları (Pre-aggregated) ──
    Route::prefix('reports')->group(function () {
        Route::get('daily-sales',             [ReportController::class, 'dailySales']);
        Route::get('payment-types',           [ReportController::class, 'paymentTypes']);
        Route::get('tax-report',              [ReportController::class, 'taxReport']);
        Route::get('product-sales',           [ReportController::class, 'productSales']);
        Route::get('complimentary-discount',  [ReportController::class, 'complimentaryDiscount']);
        Route::get('available-periods',       [ReportController::class, 'availablePeriods']);
        Route::get('department-sales',        [ReportController::class, 'departmentSales']);
        Route::get('profitability',           [ReportController::class, 'profitability']);
        Route::get('unsold-products',         [ReportController::class, 'unsoldProducts']);
        Route::get('table-occupancy',         [ReportController::class, 'tableOccupancy']);
        Route::get('staff-performance',       [ReportController::class, 'staffPerformance']);
        Route::get('waste-report',            [ReportController::class, 'wasteReport']);
    });
});
