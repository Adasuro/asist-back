<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SuperUserController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard Stats & Sections
    Route::get('/stats/counts', [DashboardController::class, 'getCounts']);
    Route::get('/sections/assigned', [DashboardController::class, 'getAssignedSections']);

    // Admin Routes
    Route::middleware('role:superusuario')->prefix('admin')->group(function () {
        Route::get('/auxiliaries', [SuperUserController::class, 'listAuxiliaries']);
        Route::post('/auxiliaries', [SuperUserController::class, 'createAuxiliar']);
        Route::patch('/auxiliaries/{id}/toggle', [SuperUserController::class, 'toggleAuxiliarStatus']);
        Route::patch('/auxiliaries/{id}/password', [SuperUserController::class, 'updateAuxiliarPassword']);
        Route::get('/grados', [SuperUserController::class, 'listGrados']);
    });
});
