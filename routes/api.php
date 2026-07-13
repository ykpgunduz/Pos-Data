<?php

use App\Http\Controllers\Api\PastOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\CariAccountController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ExpenseController;
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
    Route::post('wastages', [\App\Http\Controllers\Api\WastageController::class, 'store']);
    Route::post('backfill-tax-rates', [PastOrderController::class, 'backfillTaxRates']);

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
        Route::get('customer-report',         [ReportController::class, 'customerReport']);
    });

    // ── Müşteri Carileri ──
    Route::get('cari-accounts/list',                   [CariAccountController::class, 'index']);
    Route::post('cari-accounts/create',                [CariAccountController::class, 'store']);
    Route::get('cari-accounts/{id}',                   [CariAccountController::class, 'show']);
    Route::match(['put','patch'], 'cari-accounts/{id}/update',  [CariAccountController::class, 'update']);
    Route::delete('cari-accounts/{id}/delete',         [CariAccountController::class, 'destroy']);
    Route::post('cari-accounts/{id}/add-balance',      [CariAccountController::class, 'addBalance']);
    Route::post('cari-accounts/{id}/deduct-balance',   [CariAccountController::class, 'deductBalance']);

    // ── Tedarikçiler (Toptancılar) ──
    Route::get('suppliers/list',                       [SupplierController::class, 'index']);
    Route::post('suppliers/create',                    [SupplierController::class, 'store']);
    Route::get('suppliers/{id}',                       [SupplierController::class, 'show']);
    Route::match(['put','patch'], 'suppliers/{id}/update', [SupplierController::class, 'update']);
    Route::delete('suppliers/{id}/delete',             [SupplierController::class, 'destroy']);
    Route::post('suppliers/{id}/payment',              [SupplierController::class, 'addPayment']);

    // ── İşletme Giderleri ──
    Route::get('expenses/list',                        [ExpenseController::class, 'index']);
    Route::get('expenses/summary',                     [ExpenseController::class, 'summary']);
    Route::get('expenses/categories',                  [ExpenseController::class, 'categories']);
    Route::post('expenses/create',                     [ExpenseController::class, 'store']);
    Route::match(['put','patch'], 'expenses/{id}/update', [ExpenseController::class, 'update']);
    Route::delete('expenses/{id}/delete',              [ExpenseController::class, 'destroy']);
});

