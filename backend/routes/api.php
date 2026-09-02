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

Route::get('/teste', function () {
    return response()->json([
        'sucesso' => true,
        'mensagem' => 'Olá do Laravel! A comunicação CORS está funcionando perfeitamente.',
    ]);
});

Route::post('/login', [AuthenticatedTokenController::class, 'store']);
