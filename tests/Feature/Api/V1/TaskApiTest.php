<?php

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function actingAsUser(): User
    {
        $this->user = $this->user ?? User::factory()->create();
        Sanctum::actingAs($this->user);
        return $this->user;
    }

    public function test_index_returns_user_project_tasks(): void
    {
        $user = $this->actingAsUser();
        $project = Project::factory()->for($user, 'owner')->create();
        Task::factory(3)->for($project)->create();

        $response = $this->getJson("/api/v1/projects/{$project->id}/tasks");

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_store_creates_task_in_project(): void
    {
        $user = $this->actingAsUser();
        $project = Project::factory()->for($user, 'owner')->create();
        $taskData = Task::factory()->make()->toArray();
        $taskData['project_id'] = $project->id;

        $response = $this->postJson("/api/v1/projects/{$project->id}/tasks", $taskData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tasks', ['title' => $taskData['title'], 'project_id' => $project->id]);
    }

    public function test_filters_work(): void
    {
        $user = $this->actingAsUser();
        $project = Project::factory()->for($user, 'owner')->create();

        Task::factory()->for($project)->create(['status' => 'new', 'priority' => 'low']);
        Task::factory()->for($project)->create(['status' => 'done', 'priority' => 'high']);

        $response = $this->getJson("/api/v1/tasks?status=done&priority=high");

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }
}