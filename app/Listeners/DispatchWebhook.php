<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use App\Events\TaskStatusChanged; // Добавил недостающий импорт
use App\Jobs\ProcessWebhookRequest;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Log;

class DispatchWebhook
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void 
    {
        if ($event instanceof TaskCompleted) {
            $eventName = 'task.completed';
        } elseif ($event instanceof TaskStatusChanged) {
            $eventName = 'task.status_changed';
        } else {
            Log::warning("DispatchWebhook received unknown event type: " . get_class($event));
            return;
        }

        $project = $event->task->project;

        Log::info("DispatchWebhook: Attempting to find subscriptions for event {$eventName} on project {$project->id}.");

        $subscriptions = WebhookSubscription::where('project_id', $project->id)
                                           ->where('enabled', true)
                                           ->whereJsonContains('events', $eventName)
                                           ->get();

        Log::info("DispatchWebhook: Found {$subscriptions->count()} active subscriptions for event {$eventName} on project {$project->id}.");

        if ($subscriptions->isEmpty()) {
            Log::info("No active webhook subscriptions found for project {$project->id} for event {$eventName}.");
            return;
        }


        $payload = [
            'event_type' => $eventName,
            'task' => $event->task->toArray(),
            'old_status' => property_exists($event, 'oldStatus') ? $event->oldStatus : null,
        ];

        Log::info("DispatchWebhook: Prepared payload for {$eventName}. First keys: " . json_encode(array_keys($payload)));

        foreach ($subscriptions as $subscription) {
            Log::info("DispatchWebhook: Dispatching ProcessWebhookRequest job for subscription {$subscription->id}.");
            ProcessWebhookRequest::dispatch($subscription, $eventName, $payload);
            Log::info("DispatchWebhook: Job dispatched for subscription {$subscription->id}.");
        }
    }
}