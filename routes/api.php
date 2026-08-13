<?php

use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\WebhookSubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('projects', ProjectController::class);
    // Маршрут для создания задачи в конкретном проекте
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store']);
    Route::apiResource('tasks', TaskController::class); // Общий ресурс для задач (index, show, update, destroy)
    Route::apiResource('tasks.comments', CommentController::class)->shallow();
    // Маршрут для keyset pagination задач в проекте
    Route::get('/projects/{project}/tasks/cursor', [TaskController::class, 'indexByCursor']);
    // Маршрут для списка задач в конкретном проекте
    Route::get('/projects/{project}/tasks', [TaskController::class, 'indexForProject']);
    // Маршруты для управления веб-хуками проекта
    Route::apiResource('projects.webhooks', WebhookSubscriptionController::class)->shallow();
});

Route::prefix('v1')->group(function () {
    // Health & Monitoring
    Route::get('/health', [\App\Http\Controllers\Api\V1\HealthCheckController::class, 'health']);
    Route::get('/ready', [\App\Http\Controllers\Api\V1\HealthCheckController::class, 'ready']);
    Route::get('/metrics', [\App\Http\Controllers\Api\V1\HealthCheckController::class, 'metrics']);
});