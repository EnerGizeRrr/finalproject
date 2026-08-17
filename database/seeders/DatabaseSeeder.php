<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Создаем одного пользователя, чтобы он был владельцем проектов и автором комментариев
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Project::factory(2)
            ->has(Task::factory(5)->has(Comment::factory(2)->for($user, 'user')), 'tasks')
            ->for($user, 'owner')
            ->create();
    }
}
