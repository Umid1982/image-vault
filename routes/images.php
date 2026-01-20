<?php

use App\Http\Controllers\Api\ImageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/images', [ImageController::class, 'store']);
    Route::get('/images', [ImageController::class, 'index']);
    Route::get('/images/{image}', [ImageController::class, 'show']);
    Route::delete('/images/{image}', [ImageController::class, 'destroy']);
});
