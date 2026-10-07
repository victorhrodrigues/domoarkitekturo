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

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin', function (Request $request) {
        return response()->json(['message' => 'Acesso permitido. Você é um administrador.']);
    });
});

Route::post('/login', [AuthenticatedTokenController::class, 'store']);
