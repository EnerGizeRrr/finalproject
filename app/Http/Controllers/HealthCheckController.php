<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function health()
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready()
    {
        try {
            DB::connection()->getPdo();
            Redis::ping();
        } catch (\Exception $e) {
            return response()->json(['status' => 'not_ready'], 503);
        }

        return response()->json(['status' => 'ready']);
    }

    public function metrics()
    {
        $metrics = [
            'timestamp' => now()->toISOString(),
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
        ];

        return response()->json($metrics);
    }
}