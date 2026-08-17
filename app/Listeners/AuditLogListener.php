<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Events\TaskCompleted;
use App\Events\TaskStatusChanged;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log; 

class AuditLogListener
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
    public function handle($event): void
    {
        Log::info('AuditLogListener called for event: ' . get_class($event));

        $task = null;

        if ($event instanceof TaskCreated) {
            Log::info('Handling TaskCreated event. Task ID: ' . ($event->task->id ?? 'null'));
            $task = $event->task;
        } elseif ($event instanceof TaskStatusChanged) {
            Log::info('Handling TaskStatusChanged event. Task ID: ' . ($event->task->id ?? 'null'));
            $task = $event->task;
        } elseif ($event instanceof TaskCompleted) {
            Log::info('Handling TaskCompleted event. Task ID: ' . ($event->task->id ?? 'null'));
            $task = $event->task;
        } else {
            Log::warning('AuditLogListener: Unknown event type: ' . get_class($event));
        }

        if ($task) {
            $action = match(get_class($event)) {
                TaskCreated::class => 'created',
                TaskStatusChanged::class => 'status_changed',
                TaskCompleted::class => 'completed',
                default => 'unknown'
            };
            Log::info("AuditLogListener: Creating audit log for task: {$task->id}, action: {$action}");

            $auditLog = AuditLog::create([
                'entity_type' => 'task',
                'entity_id' => $task->id,
                'action' => $action,
                'meta' => [
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date,
                ],
            ]);

            Log::info("AuditLogListener: Created audit log entry with ID: {$auditLog->id} for task: {$task->id}, action: {$action}");
        } else {
            Log::warning('AuditLogListener: task is null for event: ' . get_class($event));
        }
    }
}