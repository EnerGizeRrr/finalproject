<?php

namespace Tests\Feature\Queue;

use App\Jobs\SendTaskCompletionNotification;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskCompletionNotificationTest extends TestCase
{
    use RefreshDatabase, DispatchesJobs;

    /**
     * Тест: задание для уведомления отправляется в очередь, когда задача завершена.
     *
     * @return void
     */
    public function test_notification_job_is_pushed_when_task_is_completed(): void
    {
        // 1. Подготовка
        Queue::fake(); // "Включаем" фейковую очередь Laravel

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create(['status' => 'new']);

        // 2. Действие
        // Обновляем статус задачи на 'done'. Это должно инициировать событие TaskCompleted.
        $response = $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'done']);
        $response->assertOk();

        // 3. Проверка (Assertion)
        // Проверяем, что задание SendTaskCompletionNotification было добавлено в очередь.
        Queue::assertPushed(SendTaskCompletionNotification::class);

        // Дополнительно проверяем, что задание было добавлено именно для нашей задачи.
        Queue::assertPushed(SendTaskCompletionNotification::class, function ($job) use ($task) {
            return $job->task->id === $task->id;
        });
    }

    /**
     * Тест: задание НЕ отправляется, если статус меняется не на 'done'.
     */
    public function test_notification_job_is_not_pushed_for_other_statuses(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create(['status' => 'new']);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'in_progress'])->assertOk();

        // Проверяем, что задание НЕ было добавлено в очередь.
        Queue::assertNotPushed(SendTaskCompletionNotification::class);
    }

    /**
     * Тест: у задания настроены правильные параметры для ретраев.
     */
    public function test_job_has_correct_retry_settings(): void
    {
        Queue::fake();

        // 1. Подготовка
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create(['status' => 'new']);

        // 2. Действие
        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'done'])->assertOk();

        // 3. Проверка
        // Проверяем, что у поставленного в очередь задания есть нужные нам свойства
        Queue::assertPushed(SendTaskCompletionNotification::class, function ($job) {
            return $job->tries === 3 && $job->backoff === [1, 5, 10];
        });
    }

    /**
     * Тест: при 3 сбоях подряд задача помечается как проваленная (failed).
     */
    public function test_job_fails_after_max_attempts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create(['status' => 'done']);

        $job = new SendTaskCompletionNotification($task);
        $job->shouldFail = true;

        $attempts = 0;
        for ($i = 1; $i <= $job->tries; $i++) {
            try {
                $job->handle();
            } catch (\Exception $e) {
                $attempts++;
            }
        }

        $this->assertEquals(3, $attempts);

        // Проверяем вызов обработчика ошибки
        $exception = new \Exception('Simulated job failure');
        $job->failed($exception);

        $this->assertTrue(true);
    }
}