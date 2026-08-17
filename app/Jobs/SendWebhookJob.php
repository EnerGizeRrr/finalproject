<?php

namespace App\Jobs;

use App\Models\WebhookAttempt;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Максимальное количество попыток
    public $tries = 3;
    // Время ожидания между попытками (backoff)
    public $backoff = [1, 5, 10]; // секунды

    public string $idempotencyKey; // Объявляем свойство

    public function __construct(
        public WebhookSubscription $subscription,
        public array $payload,
        public string $signatureHeaderName = 'X-Hub-Signature-256',
        public string $signatureAlgorithm = 'sha256'
    ) {
        $this->idempotencyKey = Str::uuid()->toString();
    }

    public function handle()
    {
        $response = null;
        $exception = null;

        try {
            // Подписываем payload
            $signature = $this->signPayload($this->payload, $this->subscription->secret);

            $response = Http::withHeaders([
                $this->signatureHeaderName => $signature,
                'Idempotency-Key' => $this->idempotencyKey,
                'Content-Type' => 'application/json',
            ])->timeout(10) // Таймаут 10 секунд
            ->post($this->subscription->url, $this->payload);

            $statusCode = $response->status();
            $responseBody = $response->body();

        } catch (\Exception $e) {
            $exception = $e;
            $statusCode = null;
            $responseBody = null;
        }

        // Логируем попытку с использованием upsert
        WebhookAttempt::upsert([
            'idempotency_key' => $this->idempotencyKey,
            'webhook_subscription_id' => $this->subscription->id,
            'status' => $exception || !$response || $response->failed() ? 'failed' : 'success',
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'error' => $exception ? $exception->getMessage() : null,
            'payload' => json_encode($this->payload),
        ], ['idempotency_key'], ['status', 'status_code', 'response_body', 'error', 'payload', 'updated_at']);

        // Если запрос завершился неудачно, выбрасываем исключение для повтора
        if ($exception || !$response || $response->failed()) {
            throw $exception ?: new \Exception("HTTP request failed with status {$statusCode}");
        }
    }

    private function signPayload(array $payload, string $secret): string
    {
        $payloadString = json_encode($payload);
        return hash_hmac($this->signatureAlgorithm, $payloadString, $secret);
    }

    /**
     * Задача провалилась окончательно.
     */
    public function failed(\Throwable $exception): void
    {
        // Здесь можно добавить дополнительную логику при окончательном провале
        Log::error("Webhook job failed permanently for subscription {$this->subscription->id}. Reason: {$exception->getMessage()}");
    }
}