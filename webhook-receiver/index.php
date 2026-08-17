<?php
// webhook-receiver/index.php

// Настройка CORS, если потребуется
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Получаем сырые данные тела запроса
$input = file_get_contents('php://input');
$headers = getallheaders();

// Логируем полученный запрос (для отладки)
error_log("Webhook received: " . print_r($_SERVER['REQUEST_URI'], true));
error_log("Headers: " . print_r($headers, true));
error_log("Body: " . $input);

// Извлекаем Idempotency-Key и возможную подпись из заголовков
$idempotencyKey = $headers['Idempotency-Key'] ?? null;
$signature = $headers['X-Hub-Signature-256'] ?? null; // Пример заголовка для HMAC-SHA256

// Симуляция случайных ошибок
$shouldFail = rand(1, 100) <= 30; // 30% вероятность ошибки

if ($shouldFail) {
    $failureType = rand(1, 2);
    if ($failureType === 1) {
        http_response_code(500);
        echo json_encode(["error" => "Internal Server Error (Simulated)"]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Bad Request (Simulated)"]);
    }
    exit;
}

// В случае успеха
http_response_code(200);
echo json_encode(["status" => "received", "idempotency_key" => $idempotencyKey]);