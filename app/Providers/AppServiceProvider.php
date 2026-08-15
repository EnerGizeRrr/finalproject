<?php

namespace App\Providers;

use App\Events\TaskCompleted;
use App\Events\TaskCreated;
use App\Events\TaskStatusChanged;
use App\Listeners\AuditLogListener;
use App\Listeners\DispatchTaskCompletionNotification;
use App\Listeners\DispatchWebhook;
use App\Models\Task;
use App\Observers\TaskObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen([
            TaskCreated::class,
            TaskStatusChanged::class,
            TaskCompleted::class,
        ], AuditLogListener::class);

        Event::listen([
            TaskCompleted::class,
        ], DispatchTaskCompletionNotification::class);

        Event::listen([
            TaskStatusChanged::class,
            TaskCompleted::class,
        ], DispatchWebhook::class);

        Task::observe(TaskObserver::class);
    }
}