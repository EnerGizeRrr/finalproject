<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendTaskCompletionNotification implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [1, 5, 10];
    public bool $shouldFail = false; // Добавляем флаг для тестирования сбоев

    public function __construct(public Task $task)
    {
        $this->uniqueId = $this->task->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = 'task_completed_notification_' . $this->task->id;
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            Log::info("Notification already sent for task {$this->task->id}, skipping.");
            return;
        }

        if ($this->shouldFail) {
            throw new \Exception('Simulated job failure for testing retries.');
        }

        $notificationContent = "Task '{$this->task->title}' (ID: {$this->task->id}) has been completed.";

        $logPath = storage_path('logs/notifications.log');
        file_put_contents($logPath, "[" . now()->toISOString() . "] " . $notificationContent . "\n", FILE_APPEND | LOCK_EX);

        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addDays(7));

        Log::info("Notification sent for task {$this->task->id}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendTaskCompletionNotification failed for task {$this->task->id}", [
            'exception' => $exception,
        ]);
    }
}