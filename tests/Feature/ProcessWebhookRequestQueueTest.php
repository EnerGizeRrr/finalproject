<?php

namespace Tests\Feature;

use App\Jobs\ProcessWebhookRequest;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB; // Для проверки failed_jobs
use Tests\TestCase;

class ProcessWebhookRequestQueueTest extends TestCase
{
    // Не используем RefreshDatabase, чтобы не мешать транзакциям при проверке БД воркером

    public function test_it_fails_after_max_attempts_when_processed_by_queue_worker(): void
    {
        // 1. Не нужно мокать HTTP, используем флаг forceFailHttp

        // 2. Создаем подписку
        $user = \App\Models\User::factory()->create();
        $project = \App\Models\Project::factory()->for($user, 'owner')->create();
        $subscription = WebhookSubscription::factory()->for($project)->create([
            'events' => ['task.completed'],
        ]);

        // 3. Очищаем failed_jobs перед тестом
        DB::table('failed_jobs')->truncate();

        // 4. Создаем задачу с флагом forceFailHttp и помещаем её в БД очередь напрямую
        //    Это обходит QUEUE_CONNECTION=sync из phpunit.xml
        $payload = ['event' => 'test'];
        $job = new ProcessWebhookRequest($subscription, 'task.completed', $payload, null, true); // forceFailHttp = true
        // Убедимся, что backoff установлены правильно в тестовом окружении
        $this->assertEquals([1, 2, 3], $job->backoff, 'Backoff intervals should be [1, 2, 3] in testing environment.');
        Queue::connection('database')->push($job);

        // 5. Проверяем, что задача в очереди (для соединения 'database')
        $this->assertEquals(1, Queue::connection('database')->size());

        // 6. Ждем, пока queue:work (запущенный вручную с --env=testing) не обработает задачу и не переместит её в failed_jobs
        //    Мы будем проверять таблицу failed_jobs с интервалом.
        $maxWaitSeconds = 120; // Увеличим время ожидания, учитывая backoff [1, 2, 3]
        $interval = 2; // Интервал проверки
        $waited = 0;

        $foundInFailedJobs = false;
        while ($waited < $maxWaitSeconds) {
            // queue:work должен обработать задачу 5 раз и переместить в failed_jobs
            if (DB::table('failed_jobs')->count() > 0) {
                $foundInFailedJobs = true;
                break; // Нашли задачу в failed_jobs
            }
            sleep($interval);
            $waited += $interval;
        }

        // 7. Проверяем, что теперь задача ПОПАЛА в failed_jobs
        $this->assertTrue($foundInFailedJobs, "Задача не попала в failed_jobs в течение {$maxWaitSeconds} секунд. Убедитесь, что 'php artisan queue:work --env=testing' запущен в другом терминале.");
    }
}