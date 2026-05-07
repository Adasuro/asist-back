<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SuperUserController;
use App\Http\Controllers\StudentController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dashboard Stats & Sections
    Route::get('/stats/counts', [DashboardController::class, 'getCounts']);
    Route::get('/sections/assigned', [DashboardController::class, 'getAssignedSections']);

    // Admin Specific Routes
    Route::middleware('role:superusuario')->prefix('admin')->group(function () {
        Route::get('/auxiliaries', [SuperUserController::class, 'listAuxiliaries']);
        Route::post('/auxiliaries', [SuperUserController::class, 'createAuxiliar']);
        Route::patch('/auxiliaries/{id}/toggle', [SuperUserController::class, 'toggleAuxiliarStatus']);
        Route::patch('/auxiliaries/{id}/password', [SuperUserController::class, 'updateAuxiliarPassword']);
    });

    // Shared Routes (Admin and Auxiliar)
    Route::middleware('role:superusuario,auxiliar')->group(function () {
        Route::get('/grados', [SuperUserController::class, 'listGrados']);
        Route::get('/students/template', [StudentController::class, 'downloadTemplate']);
        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/{id}', [StudentController::class, 'show']);
        Route::post('/students', [StudentController::class, 'store']);
        Route::patch('/students/{id}', [StudentController::class, 'update']);
        Route::delete('/students/{id}', [StudentController::class, 'destroy']);
        Route::post('/students/import', [StudentController::class, 'importCSV']);

        // Attendance Routes
        Route::post('/attendance', [App\Http\Controllers\AttendanceController::class, 'store']);
        Route::get('/attendance/section/{sectionId}/daily', [App\Http\Controllers\AttendanceController::class, 'sectionDaily']);

        // Justification Routes
        Route::post('/justifications', [App\Http\Controllers\JustificationController::class, 'store']);
        Route::get('/justifications/attendance/{asistenciaId}', [App\Http\Controllers\JustificationController::class, 'show']);

        // Report Routes
        Route::get('/reports/attendance-stats', [App\Http\Controllers\ReportController::class, 'getAttendanceStats']);
    });
});
