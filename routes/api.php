<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MemberController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('invitations/preview', [AuthController::class, 'previewInvitation'])->middleware('throttle:20,1');
    Route::post('accept-invite', [AuthController::class, 'acceptInvite'])->middleware('throttle:10,1');

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

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::apiResource('invitations', InvitationController::class)
        ->only(['index', 'store', 'destroy'])
        ->middleware('throttle:30,1');

    Route::get('/users', [UserController::class, 'index']);
    Route::patch('/users/{id}', [UserController::class, 'update'])->whereNumber('id');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->whereNumber('id');

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
});