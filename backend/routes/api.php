<?php

use App\Http\Controllers\Auth\AuthenticatedTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthenticatedTokenController::class, 'destroy']);
});

Route::post('/login', [AuthenticatedTokenController::class, 'store']);
