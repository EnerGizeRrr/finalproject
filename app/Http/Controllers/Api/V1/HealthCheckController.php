<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    /**
     * Простой эндпоинт, который говорит, что сервис жив.
     */
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Проверяет готовность сервиса к приему трафика (подключения к БД и Redis).
     */
    public function ready(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            Redis::connection()->ping();
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service is not ready',
                'error' => $e->getMessage(),
            ], 503);
        }

        return response()->json(['status' => 'ready']);
    }

    /**
     * Возвращает простые метрики приложения.
     */
    public function metrics(): JsonResponse
    {
        $metrics = [
            'memory_peak_usage' => memory_get_peak_usage(true) / 1024 / 1024 . 'MB',
            'laravel_version' => app()->version(),
        ];

        return response()->json($metrics);
    }
}
