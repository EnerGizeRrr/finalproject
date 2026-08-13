<?php

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_project_and_tasks(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $project = Project::factory()->for($owner, 'owner')->create();
        ProjectUser::factory()->for($project)->for($member)->create();
        $task = Task::factory()->for($project)->create();

        $this->getJson("/api/v1/projects/{$project->id}")
             ->assertStatus(200);

        $this->getJson("/api/v1/projects/{$project->id}/tasks")
             ->assertStatus(200);
    }

    public function test_member_cannot_delete_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $project = Project::factory()->for($owner, 'owner')->create();
        ProjectUser::factory()->for($project)->for($member)->create();

        $this->deleteJson("/api/v1/projects/{$project->id}")
             ->assertStatus(403);
    }
}