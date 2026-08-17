<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Используем существующий ID или генерируем новый
        $requestId = $request->header('X-Request-ID') ?: (string) Str::uuid();

        // Добавляем ID в контекст всех логов для этого запроса
        Log::withContext([
            'request-id' => $requestId
        ]);

        // Выполняем запрос
        $response = $next($request);

        // Добавляем ID в заголовок ответа
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
