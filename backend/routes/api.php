<?php

use App\Http\Controllers\Auth\AuthenticatedTokenController;
use App\Http\Controllers\CategoryController;
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

    Route::apiResource('categories', CategoryController::class)->except('index', 'show');
});

Route::post('/login', [AuthenticatedTokenController::class, 'store']);

// Rota Category
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
