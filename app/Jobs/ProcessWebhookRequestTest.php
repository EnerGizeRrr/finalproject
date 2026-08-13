<?php

namespace Tests\Feature\Jobs;

use App\Events\TaskCompleted;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Queue\ManuallyFailedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessWebhookRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_webhook_with_a_valid_signature(): void
    {
        // 1. Подготовка
        // Перехватываем все исходящие HTTP-запросы
        Http::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create();

        // Создаем подписку, которая слушает событие 'task.completed'
        $subscription = WebhookSubscription::factory()->for($project)->create([
            'url' => 'https://example.com/test-webhook',
            'events' => ['task.completed'],
            'enabled' => true,
        ]);

        // 2. Действие
        // Отправляем событие, которое должно запустить отправку веб-хука
        TaskCompleted::dispatch($task);

        // 3. Проверки
        // Вычисляем ожидаемую подпись точно так же, как это делает наша задача
        $payload = [
            'event_type' => 'task.completed',
            'task' => $task->toArray(),
            // Для TaskCompleted oldStatus равен null, как и в DispatchWebhook
            'old_status' => property_exists($task, 'oldStatus') ? $task->oldStatus : null,
        ];
        $jsonPayload = json_encode($payload);
        $expectedSignature = hash_hmac('sha256', $jsonPayload, $subscription->secret);

        // Проверяем, что был отправлен запрос на нужный URL
        Http::assertSent(function ($request) use ($subscription, $expectedSignature, $payload) {
            // Проверяем URL, тело запроса и наличие правильной подписи в заголовке
            return $request->url() === $subscription->url &&
                   $request->hasHeader('X-Task-Manager-Signature', $expectedSignature) &&
                   $request->data() === $payload;
        });
    }

        public function test_it_logs_a_successful_webhook_attempt(): void
    {
        // 1. Подготовка
        // Имитируем успешный ответ от сервера
        Http::fake([
            '*' => Http::response(['message' => 'Webhook received'], 200),
        ]);

        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subscription = WebhookSubscription::factory()->for($project)->create([
            'events' => ['task.completed'],
        ]);

        // 2. Действие
        TaskCompleted::dispatch($task);

        // 3. Проверка
        // Убеждаемся, что в базе данных появилась запись об успешной попытке
        $this->assertDatabaseHas('webhook_attempts', [
            'webhook_subscription_id' => $subscription->id,
            'status' => 'success',
            'status_code' => 200,
        ]);
    }

    public function test_it_logs_a_failed_webhook_attempt(): void
    {
        // 1. Подготовка
        // Имитируем ошибку сервера
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subscription = WebhookSubscription::factory()->for($project)->create([
            'events' => ['task.completed'],
        ]);

        // 2. Действие
        TaskCompleted::dispatch($task);

        // 3. Проверка
        // Убеждаемся, что в базе данных появилась запись о неуспешной попытке
        $this->assertDatabaseHas('webhook_attempts', [
            'webhook_subscription_id' => $subscription->id,
            'status' => 'failed',
            'status_code' => 500,
        ]);
    }

    public function test_it_is_retried_on_server_error(): void
    {
        // 1. Подготовка
        // Имитируем ошибку сервера (503 Service Unavailable)
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $project = Project::factory()->create();
        $subscription = WebhookSubscription::factory()->for($project)->create([
            'events' => ['task.completed'],
        ]);
        $payload = ['test' => 'data'];

        // 2. Действие и Проверка
        // Мы ожидаем, что задача будет "провалена" с помощью ManuallyFailedException,
        // что является сигналом для воркера очередей о необходимости повторной попытки.
        $this->expectException(ManuallyFailedException::class);

        // Создаем и выполняем задачу напрямую, чтобы проверить её поведение
        $job = new \App\Jobs\ProcessWebhookRequest($subscription, 'task.completed', $payload);
        $job->handle();

        // 3. Дополнительная проверка
        // Убеждаемся, что в базе данных появилась запись о неуспешной попытке
        $this->assertDatabaseHas('webhook_attempts', [
            'webhook_subscription_id' => $subscription->id,
            'status' => 'failed',
            'status_code' => 503,
        ]);
    }
}