<?php

namespace App\Jobs;

use App\Models\WebhookAttempt;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessWebhookRequest implements ShouldQueue, ShouldBeUnique // Реализуем оба интерфейса
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff;

    public string $idempotencyKey;

    public bool $forceFailHttp = false; // Добавим флаг

    public function __construct(
        public WebhookSubscription $subscription,
        public string $eventName,
        public array $payload,
        ?string $idempotencyKey = null,
        bool $forceFailHttp = false // Принимаем флаг в конструкторе
    ) {
        $this->idempotencyKey = $idempotencyKey ?? Str::uuid()->toString();
        $this->forceFailHttp = $forceFailHttp;

        // Устанавливаем backoff в зависимости от окружения
        if (app()->environment('testing')) {
            $this->backoff = [1, 2, 3]; // Короткие интервалы для тестирования
        } else {
            $this->backoff = [60, 300, 900]; // Стандартные интервалы для production
        }
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->idempotencyKey;
    }

    /**
     * Get the number of seconds before the job should timeout.
     */
    public function uniqueFor(): int
    {
        // Задача будет уникальной в течение 1 часа
        return 3600;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $jsonPayload = json_encode($this->payload);
        $signature = hash_hmac('sha256', $jsonPayload, $this->subscription->secret);

        Log::info("Sending webhook for event {$this->eventName} to {$this->subscription->url}. Attempt #{$this->attempts()}");

        $startTime = microtime(true);
        $response = null;

        try {
            // Если forceFailHttp = true, мокаем HTTP-запрос на 500
            if ($this->forceFailHttp) {
                Http::fake([
                    '*' => Http::response('Simulated Server Error', 500),
                ]);
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Task-Manager-Signature' => $signature,
                'Idempotency-Key' => $this->idempotencyKey,
            ])->timeout(15)->post($this->subscription->url, $this->payload);

            // Если forceFailHttp был активен, отключаем фейк, чтобы не влиять на другие части приложения
            if ($this->forceFailHttp) {
                Http::fake(); // Сбрасываем фейк
            }

            $responseTime = (int) round((microtime(true) - $startTime) * 1000);

            // Используем upsert для записи попытки
            WebhookAttempt::upsert([
                'idempotency_key' => $this->idempotencyKey,
                'webhook_subscription_id' => $this->subscription->id,
                'status' => $response->successful() ? 'success' : 'failed',
                'status_code' => $response->status(),
                'response_time' => $responseTime,
                'error' => $response->failed() ? "HTTP status {$response->status()}" : null,
                'response_body' => $response->body(),
                'payload' => $jsonPayload, // Используем json_encode($this->payload)
                // 'created_at' и 'updated_at' будут автоматически обновлены
            ], ['idempotency_key'], ['status', 'status_code', 'response_time', 'error', 'response_body', 'payload', 'updated_at']); // Указываем, что payload тоже может обновиться при конфликте

            Log::info("Webhook request to {$this->subscription->url} finished with status {$response->status()}.");

            if ($response->serverError()) {
                throw new \Exception("Server error: {$response->status()}");
            }

        } catch (\Throwable $e) {
            $responseTime = (int) round((microtime(true) - $startTime) * 1000);

            // Используем upsert и для ошибок
            WebhookAttempt::upsert([
                'idempotency_key' => $this->idempotencyKey,
                'webhook_subscription_id' => $this->subscription->id,
                'status' => 'failed',
                'response_time' => $responseTime,
                'error' => $e->getMessage(),
                'response_body' => $e->getMessage(),
                'payload' => json_encode($this->payload), // Используем json_encode($this->payload)
                // 'created_at' и 'updated_at' будут автоматически обновлены
            ], ['idempotency_key'], ['status', 'response_time', 'error', 'response_body', 'payload', 'updated_at']); // Указываем, что payload тоже может обновиться при конфликте

            Log::error("Failed to send webhook request to {$this->subscription->url}. Error: " . $e->getMessage());

            throw $e;
        }
    }
}