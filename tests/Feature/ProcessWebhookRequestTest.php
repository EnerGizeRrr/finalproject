<?php

namespace Tests\Feature;

use App\Events\TaskCompleted;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessWebhookRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_a_webhook_with_a_valid_signature(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $project = Project::factory()->for($user, 'owner')->create();
        $task = Task::factory()->for($project)->create();

        $subscription = WebhookSubscription::factory()->for($project)->create([
            'url' => 'https://example.com/test-webhook',
            'events' => ['task.completed'],
            'enabled' => true,
        ]);

        TaskCompleted::dispatch($task);

        $payload = [
            'event_type' => 'task.completed',
            'task' => $task->toArray(),
            'old_status' => null,
        ];
        $jsonPayload = json_encode($payload);
        $expectedSignature = hash_hmac('sha256', $jsonPayload, $subscription->secret);

        Http::assertSent(function ($request) use ($subscription, $expectedSignature, $payload) {
            return $request->url() === $subscription->url &&
                $request->hasHeader('X-Task-Manager-Signature', $expectedSignature) &&
                $request->data() === $payload;
        });
    }

    public function test_it_logs_a_successful_webhook_attempt(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Webhook received'], 200),
        ]);

        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subscription = WebhookSubscription::factory()->for($project)->create([
            'events' => ['task.completed'],
        ]);

        TaskCompleted::dispatch($task);

        $this->assertDatabaseHas('webhook_attempts', [
            'webhook_subscription_id' => $subscription->id,
            'status' => 'success',
            'status_code' => 200,
        ]);
    }

    public function test_it_logs_a_failed_webhook_attempt(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subscription = WebhookSubscription::factory()->for($project)->create([
            'events' => ['task.completed'],
        ]);

        try {
            TaskCompleted::dispatch($task);
        } catch (\Exception $e) {
            // expected 500 exception for retry
        }

        $this->assertDatabaseHas('webhook_attempts', [
            'webhook_subscription_id' => $subscription->id,
            'status' => 'failed',
            'status_code' => 500,
        ]);
    }

    public function test_it_throws_exception_on_server_error_for_retry(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $project = Project::factory()->create();
        $subscription = WebhookSubscription::factory()->for($project)->create([
            'events' => ['task.completed'],
        ]);
        $payload = ['test' => 'data'];

        $this->expectException(\Exception::class);

        $job = new \App\Jobs\ProcessWebhookRequest($subscription, 'task.completed', $payload);
        $job->handle();
    }
}
