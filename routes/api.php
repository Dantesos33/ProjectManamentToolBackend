<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Cookie-authenticated, so they get the origin check
    Route::middleware(['trusted.origin', 'throttle:30,1'])->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);

    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('projects.tasks', TaskController::class)->shallow();
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus']);

    Route::get('/dashboard/stats', DashboardController::class);
    Route::get('/members', MemberController::class);
});