<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            // Предполагаем, что пользователь с id=1 существует.
            'user_id' => User::first() ?? User::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
