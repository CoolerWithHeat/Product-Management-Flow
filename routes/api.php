<?php

use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\PurchaseRefundController;
use Illuminate\Support\Facades\Route;

Route::post('/batches', [BatchController::class, 'store'])->name('api.batches.store');
Route::post('/batches/{batch}/refunds', [PurchaseRefundController::class, 'store'])->name('api.batches.refunds.store');
