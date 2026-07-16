<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\SectorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('events', EventController::class);
    Route::apiResource('events.sectors', SectorController::class)->scoped();
});
