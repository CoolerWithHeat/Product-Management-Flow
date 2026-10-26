<?php

use App\Http\Controllers\Api\AvailableProductController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\BatchProfitController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderRefundController;
use App\Http\Controllers\Api\PurchaseRefundController;
use App\Http\Controllers\Api\StockReportController;
use Illuminate\Support\Facades\Route;

Route::post('/batches', [BatchController::class, 'store'])->name('api.batches.store');
Route::post('/batches/{batch}/refunds', [PurchaseRefundController::class, 'store'])->name('api.batches.refunds.store');

Route::get('/products/available', [AvailableProductController::class, 'index'])->name('api.products.available');
Route::get('/reports/stock', [StockReportController::class, 'index'])->name('api.reports.stock');

Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');
Route::post('/orders/{order}/refunds', [OrderRefundController::class, 'store'])->name('api.orders.refunds.store');

Route::get('/reports/batch-profit', [BatchProfitController::class, 'index'])->name('api.reports.batch-profit');
