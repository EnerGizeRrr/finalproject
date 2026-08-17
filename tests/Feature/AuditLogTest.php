<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_created_when_task_status_changes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create(['status' => 'new']);

        $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'done']);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'action' => 'status_changed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'action' => 'completed',
        ]);
    }
}