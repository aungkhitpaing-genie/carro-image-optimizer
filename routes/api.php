<?php

use App\Http\Controllers\Api\GenerateVariantController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->group(function () {
    Route::get('/status', StatusController::class);
    Route::post('/variants/generate', GenerateVariantController::class);
});
