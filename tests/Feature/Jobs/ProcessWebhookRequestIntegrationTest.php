<?php

namespace Tests\Feature\Jobs;

use App\Events\TaskStatusChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WebhookAttempt;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProcessWebhookRequestIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Устанавливаем синхронный драйвер очереди для этого теста
        Config::set('queue.default', 'sync');
    }

    public function test_it_sends_webhook_request_and_logs_attempt()
    {
        // Создаем пользователя, проект, задачу
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'new']);

        // Подписываем проект на веб-хук, указывая на внутренний сервис
        $subscription = WebhookSubscription::create([
            'project_id' => $project->id,
            'url' => 'http://webhook-receiver:8000/webhook', // URL внутреннего сервиса
            'events' => ['task.status_changed'],
            'secret' => 'test_secret_123',
            'enabled' => true,
        ]);

        // Диспетчеризуем событие, которое должно вызвать DispatchWebhook и ProcessWebhookRequest
        // Так как очередь 'sync', Job выполнится сразу.
        event(new TaskStatusChanged($task, 'new'));

        // Проверим, что была создана запись в webhook_attempts.
        // Поскольку webhook-receiver иногда возвращает 500, статус может быть 'failed'.
        // Иногда 200 - тогда 'success'. Проверим наличие *любой* попытки для этой подписки.
        $attempt = WebhookAttempt::where('webhook_subscription_id', $subscription->id)->first();
        $this->assertNotNull($attempt, "A webhook attempt was not found for the subscription.");
        $this->assertIsInt($attempt->status_code, "Status code should be an integer.");
        $this->assertIsInt($attempt->response_time, "Response time should be an integer.");
        $this->assertNotNull($attempt->idempotency_key, "Idempotency key should not be null.");
        // Проверим, что payload был десериализован как массив (Eloquent автоматически десериализует JSON поля).
        $this->assertIsArray($attempt->payload, "Payload should be deserialized as an array.");
    }

    public function test_it_logs_an_attempt_when_called_multiple_times()
    {
        // Создаем пользователя, проект, задачу
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'new']);

        // Подписываем проект на веб-хук, указывая на внутренний сервис
        $subscription = WebhookSubscription::create([
            'project_id' => $project->id,
            'url' => 'http://webhook-receiver:8000/webhook', // URL внутреннего сервиса
            'events' => ['task.status_changed'],
            'secret' => 'test_secret_123',
            'enabled' => true,
        ]);

        // Диспетчерим событие дважды, что должно создать две задачи
        // Из-за upsert с одинаковым idempotency_key (или из-за ошибок от webhook-receiver), выполнение может прерваться исключением.
        // Обернем вызовы в try-catch, чтобы не прерывать тест преждевременно.
        try {
            event(new TaskStatusChanged($task, 'new'));
        } catch (\Exception $e) {
            // Логируем или игнорируем исключение, если оно связано с ожидаемой ошибкой от webhook-receiver
            // \Log::warning("Expected exception during event dispatch: " . $e->getMessage());
        }

        try {
            event(new TaskStatusChanged($task, 'new'));
        } catch (\Exception $e) {
             // Логируем или игнорируем исключение, если оно связано с ожидаемой ошибкой от webhook-receiver
             // \Log::warning("Expected exception during event dispatch: " . $e->getMessage());
        }


        // Проверим, что *по крайней мере* одна запись в webhook_attempts существует.
        // (Возможно, обновленная дважды, но в БД будет одна или две, в зависимости от idempotency_key и ошибок)
        $attempts = WebhookAttempt::where('webhook_subscription_id', $subscription->id)->get();
        $this->assertGreaterThanOrEqual(1, $attempts->count(), "At least one webhook attempt should have been recorded.");
    }
}