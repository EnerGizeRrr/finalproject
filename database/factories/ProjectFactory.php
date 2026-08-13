<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            // Предполагаем, что пользователь с id=1 существует.
            // В реальном проекте лучше получать случайного пользователя.
            'owner_id' => User::first() ?? User::factory(),
        ];
    }
}
