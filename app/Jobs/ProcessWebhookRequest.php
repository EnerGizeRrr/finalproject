<?php

namespace App\Jobs;

use App\Models\WebhookAttempt;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Добавляем этот импорт
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessWebhookRequest implements ShouldQueue
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
    public array $backoff = [60, 300, 900]; // 1 min, 5 mins, 15 mins

    public function __construct(
        public WebhookSubscription $subscription,
        public string $eventName,
        public array $payload
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $idempotencyKey = Str::uuid()->toString();
        $jsonPayload = json_encode($this->payload);
        $signature = hash_hmac('sha256', $jsonPayload, $this->subscription->secret);

        Log::info("Sending webhook for event {$this->eventName} to {$this->subscription->url}. Attempt #{$this->attempts()}");

        $response = null;
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Task-Manager-Signature' => $signature,
                'Idempotency-Key' => $idempotencyKey,
            ])->timeout(15)->post($this->subscription->url, $this->payload);

            WebhookAttempt::create([
                'webhook_subscription_id' => $this->subscription->id,
                'status' => $response->successful() ? 'success' : 'failed',
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'payload' => $this->payload,
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info("Webhook request to {$this->subscription->url} finished with status {$response->status()}.");

            // Если ответ сервера - это ошибка 5xx, "проваливаем" задачу, чтобы она была повторена.
            // Ошибки 4xx (клиентские) не повторяем.
            if ($response->serverError()) {
                $this->fail(new \Exception("Server error: {$response->status()}"));
            }

        } catch (\Throwable $e) {
            WebhookAttempt::create([
                'webhook_subscription_id' => $this->subscription->id,
                'status' => 'failed',
                'response_body' => $e->getMessage(),
                'payload' => $this->payload,
                'idempotency_key' => $idempotencyKey,
            ]);
            Log::error("Failed to send webhook request to {$this->subscription->url}. Error: " . $e->getMessage());

            // "Проваливаем" задачу, чтобы система очередей попробовала выполнить её снова.
            $this->fail($e);
        }
    }
}