<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
   Route::resource('events', EventController::class);
});
