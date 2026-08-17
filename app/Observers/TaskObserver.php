<?php

namespace App\Observers;

use App\Enums\TaskStatus; // Импортируем Enum
use App\Events\TaskCompleted;
use App\Events\TaskCreated;
use App\Events\TaskStatusChanged;
use App\Models\Task;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log; // Добавляем импорт Log

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        Log::info('TaskObserver: created called.', ['task_id' => $task->id]);
        Event::dispatch(new TaskCreated($task));
    }

    /**
     * Handle the Task "updated" event.
     */
    public function updated(Task $task): void
    {
        Log::info('TaskObserver: updated called.', ['task_id' => $task->id]);
        Log::info("TaskObserver: isDirty('status'): " . var_export($task->isDirty('status'), true));
        Log::info("TaskObserver: Original status: " . ($task->getOriginal('status') instanceof TaskStatus ? $task->getOriginal('status')->value : $task->getOriginal('status')));
        Log::info("TaskObserver: New status (raw attribute): " . ($task->getAttribute('status') instanceof \App\Enums\TaskStatus ? $task->getAttribute('status')->value : $task->getAttribute('status')));
        Log::info("TaskObserver: New status (casted): " . $task->status->value);


        if ($task->wasChanged('status')) {
            $oldStatusValue = $task->getOriginal('status');


            if ($oldStatusValue instanceof TaskStatus) {
                $oldStatus = $oldStatusValue->value;
            } else {
                $oldStatus = $oldStatusValue;
            }

            Log::info("TaskObserver: Dispatching TaskStatusChanged for task {$task->id}, old: $oldStatus, new: {$task->status->value}");
            Event::dispatch(new TaskStatusChanged($task, $oldStatus));


            if ($task->status->value === 'done') {
                Log::info("TaskObserver: Dispatching TaskCompleted for task {$task->id}");
                Event::dispatch(new TaskCompleted($task));
            }
            Log::info("TaskObserver: Events dispatched for task {$task->id}.");
        } else {
             Log::info("TaskObserver: Status was not dirty, skipping events.");
        }
    }

    /**
     * Handle the Task "deleted" event.
     */
    public function deleted(Task $task): void
    {
        Log::info('TaskObserver: deleted called.', ['task_id' => $task->id]);
    }

    /**
     * Handle the Task "restored" event.
     */
    public function restored(Task $task): void
    {
        Log::info('TaskObserver: restored called.', ['task_id' => $task->id]);
    }

    /**
     * Handle the Task "force deleted" event.
     */
    public function forceDeleted(Task $task): void
    {
        Log::info('TaskObserver: forceDeleted called.', ['task_id' => $task->id]);
    }
}