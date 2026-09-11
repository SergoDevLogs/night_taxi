<?php

use App\Http\Controllers\Api\BoxController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\PackingController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Товары
    Route::get   ('/products',      [ProductController::class, 'index']);
    Route::post  ('/products',      [ProductController::class, 'store']);
    Route::get   ('/products/{id}', [ProductController::class, 'show']);
    Route::put   ('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Коробки
    Route::get   ('/boxes',      [BoxController::class, 'index']);
    Route::post  ('/boxes',      [BoxController::class, 'store']);
    Route::get   ('/boxes/{id}', [BoxController::class, 'show']);
    Route::put   ('/boxes/{id}', [BoxController::class, 'update']);
    Route::delete('/boxes/{id}', [BoxController::class, 'destroy']);

    // Заказы
    Route::get  ('/orders',             [OrderController::class, 'index']);
    Route::post ('/orders',             [OrderController::class, 'store']);
    Route::get  ('/orders/{id}',        [OrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Позиции заказа
    Route::get ('/orders/{orderId}/items', [OrderItemController::class, 'index']);
    Route::post('/orders/{orderId}/items', [OrderItemController::class, 'store']);

    // Упаковка
    // ВАЖНО: /packing/raw — до /packing, если появятся параметрические подсчёты
    Route::get ('/orders/{id}/packing/raw', [PackingController::class, 'raw']);
    Route::get ('/orders/{id}/packing.csv', [PackingController::class, 'csv']);
    Route::get ('/orders/{id}/packing.pdf', [PackingController::class, 'pdf']);
    Route::get ('/orders/{id}/packing',     [PackingController::class, 'show']);
    Route::post('/orders/{id}/pack',        [PackingController::class, 'pack']);
});
