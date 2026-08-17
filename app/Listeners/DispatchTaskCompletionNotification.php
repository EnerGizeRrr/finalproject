<?php

namespace App\Listeners;

use App\Events\TaskCompleted;
use App\Jobs\SendTaskCompletionNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchTaskCompletionNotification
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
    public function handle(TaskCompleted $event): void
    {
        SendTaskCompletionNotification::dispatch($event->task);
    }
}