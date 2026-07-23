<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('events', EventController::class);
    Route::apiResource('events.sectors', SectorController::class)->scoped();
    Route::post('events/{event}/sectors/{sector}/seats/generate', [SeatController::class, 'generate'])
        ->scopeBindings();
    Route::apiResource('events.sectors.seats', SeatController::class)->scoped();

    Route::apiResource('events.orders', OrderController::class)->only(['index', 'store', 'show'])->scoped();
    Route::patch('events/{event}/orders/{order}/payment-provider', [OrderController::class, 'attachPaymentProvider'])
        ->scopeBindings();
    Route::post('events/{event}/orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->scopeBindings();
    Route::post('orders/{paymentProviderId}/confirm-payment', [OrderController::class, 'confirmPayment']);
    Route::get('events/{event}/users/{userId}/limit', [UserController::class, 'getLimit']);
});
