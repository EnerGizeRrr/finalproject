<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FailingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Установим количество попыток в 3

    public function __construct()
    {
        //
    }

    public function handle()
    {
        // Всегда бросаем исключение
        throw new \Exception('This job is designed to fail.');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Можно добавить логику для логирования в файл или БД при неудаче
        // \Log::error("FailingJob failed permanently: " . $exception->getMessage());
    }
}