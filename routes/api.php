<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->name('api.')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

Route::middleware('auth:sanctum')->post('/sync', [SyncController::class, 'sync'])->name('api.sync');
