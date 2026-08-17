<?php

namespace App\Listeners;

use App\Events\TaskStatusChanged;
use App\Jobs\SendWebhookJob;
use App\Models\WebhookSubscription;

class SendWebhookOnTaskStatusChanged
{
    /**
     * Handle the event.
     */
    public function handle(TaskStatusChanged $event): void
    {
        // Найти все активные веб-хуки для проекта задачи, которые слушают событие 'task.status_changed'
        $subscriptions = WebhookSubscription::where('project_id', $event->task->project_id)
            ->where('enabled', true)
            ->whereJsonContains('events', 'task.status_changed') // Предполагается, что 'events' — это JSON-массив
            ->get();

        foreach ($subscriptions as $subscription) {
            $payload = [
                'event' => 'task.status_changed',
                'task_id' => $event->task->id,
                'old_status' => $event->oldStatus,
                'new_status' => $event->task->status->value,
                'timestamp' => now()->toISOString(),
            ];

            // Отправить Job в очередь
            SendWebhookJob::dispatch($subscription, $payload);
        }
    }
}