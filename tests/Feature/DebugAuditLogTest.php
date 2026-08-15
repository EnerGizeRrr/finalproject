<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\Log; // Добавляем фасад Log

class DebugAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_task_update_and_events(): void
    {
        Log::info('DebugAuditLogTest: Starting test.');

        $user = User::factory()->create();
        Log::info('DebugAuditLogTest: User created.', ['user_id' => $user->id]);
        Sanctum::actingAs($user);
        Log::info('DebugAuditLogTest: Sanctum::actingAs called.');

        $project = Project::factory()->for($user, 'owner')->create();
        Log::info('DebugAuditLogTest: Project created.', ['project_id' => $project->id]);

        $task = Task::factory()->for($project)->create(['status' => 'new']);
        Log::info('DebugAuditLogTest: Task created.', ['task_id' => $task->id, 'status' => $task->status->value]);

        $initialLogsCount = AuditLog::count();
        Log::info('DebugAuditLogTest: Initial audit log count.', ['count' => $initialLogsCount]);

        Log::info('DebugAuditLogTest: About to call putJson to update task status.');
        $response = $this->putJson("/api/v1/tasks/{$task->id}", ['status' => 'done']);
        Log::info('DebugAuditLogTest: putJson call completed.', ['status_code' => $response->getStatusCode()]);
        
        if ($response->getStatusCode() === 422) {
            Log::error('DebugAuditLogTest: Validation error occurred.', ['errors' => $response->json('errors')]);
        }

        $logsAfterRequest = AuditLog::all();
        Log::info('DebugAuditLogTest: Audit logs after request.', $logsAfterRequest->toArray());

        $finalLogsCount = AuditLog::count();
        Log::info('DebugAuditLogTest: Final audit log count.', ['count' => $finalLogsCount]);

        $task->refresh();
        Log::info('DebugAuditLogTest: Task status after refresh.', ['status' => $task->status->value]);
        
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