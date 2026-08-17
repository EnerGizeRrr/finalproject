<?php

namespace Tests\Feature\Jobs;

use App\Jobs\FailingJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FailingJobTest extends TestCase
{
    use RefreshDatabase; // Теперь используем RefreshDatabase

    protected function setUp(): void
    {
        parent::setUp();
        // Убедимся, что используется database драйвер очереди
        Config::set('queue.default', 'database');
        Config::set('queue.connections.database.table', 'jobs');
        
        // Очистка таблиц выполняется через RefreshDatabase, поэтому не нужно:
        // DB::table('jobs')->truncate();
        // DB::table('failed_jobs')->truncate();
    }

    public function test_job_fails_after_three_attempts_and_goes_to_failed_jobs()
    {
        // Диспетчерим задачу
        FailingJob::dispatch();

        // Проверяем, что задача появилась в таблице jobs
        $this->assertDatabaseHas('jobs', [
            'attempts' => 0, // Начальное значение
        ]);

        // Запускаем обработчик очереди один раз. Это увеличит attempts до 1 и вернет задачу обратно в очередь.
        Artisan::call('queue:work --once --max-jobs=1 --sleep=0');
        $this->assertDatabaseHas('jobs', [
            'attempts' => 1,
        ]);

        // Запускаем снова. attempts = 2.
        Artisan::call('queue:work --once --max-jobs=1 --sleep=0');
        $this->assertDatabaseHas('jobs', [
            'attempts' => 2,
        ]);

        // Запускаем третий раз. attempts = 3. Задача должна исчерпать попытки и перейти в failed_jobs.
        Artisan::call('queue:work --once --max-jobs=1 --sleep=0');

        // Проверяем, что задача исчезла из таблицы jobs
        $this->assertDatabaseMissing('jobs', []);

        // Проверяем, что задача появилась в таблице failed_jobs
        $this->assertDatabaseHas('failed_jobs', [
            'connection' => 'database',
        ]);

        // Проверим, что в failed_jobs есть запись с нашим job
        $failedJobRecord = DB::table('failed_jobs')->where('connection', 'database')->first();
        $this->assertNotNull($failedJobRecord);

        // Ищем имя класса в payload как displayName или commandName
        $this->assertTrue(
            str_contains($failedJobRecord->payload, FailingJob::class) ||
            str_contains($failedJobRecord->payload, str_replace('\\', '\\\\', FailingJob::class)), // Проверка на экранированный слэш
            "Payload does not contain FailingJob class name. Payload: " . $failedJobRecord->payload
        );
    }
}