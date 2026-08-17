<?php

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected User $otherUser;
    protected Project $project;
    protected WebhookSubscription $webhook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->project = Project::factory()->for($this->owner, 'owner')->create();
        ProjectUser::factory()->for($this->project)->for($this->member)->create();

        // $this->webhook = WebhookSubscription::factory()->for($this->project)->create(); // Убираем создание здесь
    }

    // --- Unauthenticated Access Tests ---

    public function test_unauthenticated_user_cannot_access_webhook_endpoints(): void
    {
        $webhook = WebhookSubscription::factory()->for($this->project)->create();
        $this->getJson("/api/v1/projects/{$this->project->id}/webhooks")->assertUnauthorized();
        $this->postJson("/api/v1/projects/{$this->project->id}/webhooks", [])->assertUnauthorized();
        $this->getJson("/api/v1/webhooks/{$webhook->id}")->assertUnauthorized();
        $this->putJson("/api/v1/webhooks/{$webhook->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/v1/webhooks/{$webhook->id}")->assertUnauthorized();
    }

    // --- Owner Access Tests ---

    public function test_owner_can_list_webhooks_for_their_project(): void
    {
        Sanctum::actingAs($this->owner);
        WebhookSubscription::factory(2)->for($this->project)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/webhooks");

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }

    public function test_owner_can_create_webhook_for_their_project(): void
    {
        Sanctum::actingAs($this->owner);
        $webhookData = [
            'url' => 'https://example.com/new-webhook',
            'events' => ['task.completed'],
        ];

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/webhooks", $webhookData);

        $response->assertCreated()
                 ->assertJsonFragment(['url' => $webhookData['url']])
                 ->assertJsonStructure(['data' => ['id', 'project_id', 'url', 'events', 'enabled'], 'secret']);
        
        // Explicitly retrieve the created webhook and assert its properties
        $createdWebhook = WebhookSubscription::where('project_id', $this->project->id)
                                             ->where('url', $webhookData['url'])
                                             ->first();
        $this->assertNotNull($createdWebhook, 'Webhook was not found in the database after creation.');
        $this->assertEquals($webhookData['events'], $createdWebhook->events, 'Events array does not match the created webhook.');

        $this->assertDatabaseHas('webhook_subscriptions', [ // Assert other fields with assertDatabaseHas
            'project_id' => $this->project->id,
            'url' => $webhookData['url'],
        ]);
        // Проверяем, что секрет возвращается только при создании
        $this->assertArrayHasKey('secret', $response->json());
    }

    public function test_owner_can_view_their_webhook(): void
    {
        $webhook = WebhookSubscription::factory()->for($this->project)->create();
        Sanctum::actingAs($this->owner);

        $response = $this->getJson("/api/v1/webhooks/{$webhook->id}");

        $response->assertOk()
                 ->assertJsonFragment(['url' => $webhook->url]);
        // Проверяем, что секрет НЕ возвращается при просмотре
        $this->assertArrayNotHasKey('secret', $response->json('data'));
    }

    public function test_owner_can_update_their_webhook(): void
    {
        $webhook = WebhookSubscription::factory()->for($this->project)->create();
        Sanctum::actingAs($this->owner);
        $updatedData = [
            'url' => 'https://example.com/updated-webhook',
            'enabled' => false,
        ];

        $response = $this->putJson("/api/v1/webhooks/{$webhook->id}", $updatedData);

        $response->assertOk()
                 ->assertJsonFragment(['url' => $updatedData['url'], 'enabled' => false]);

        $this->assertDatabaseHas('webhook_subscriptions', [
            'id' => $webhook->id,
            'url' => $updatedData['url'],
            'enabled' => false,
        ]);
    }

    public function test_owner_can_delete_their_webhook(): void
    {
        $webhook = WebhookSubscription::factory()->for($this->project)->create();
        Sanctum::actingAs($this->owner);

        $this->deleteJson("/api/v1/webhooks/{$webhook->id}")
             ->assertNoContent();

        $this->assertDatabaseMissing('webhook_subscriptions', ['id' => $webhook->id]);
    }

    // --- Member Access Tests ---

    public function test_member_can_list_webhooks_for_project(): void
    {
        Sanctum::actingAs($this->member);
        WebhookSubscription::factory()->for($this->project)->create();
        $response = $this->getJson("/api/v1/projects/{$this->project->id}/webhooks");
        $response->assertOk();
    }

    public function test_member_can_view_webhook_for_project(): void
    {
        $webhook = WebhookSubscription::factory()->for($this->project)->create();
        Sanctum::actingAs($this->member);
        $response = $this->getJson("/api/v1/webhooks/{$webhook->id}");
        $response->assertOk();
    }

    public function test_member_cannot_create_webhook_for_project(): void
    {
        Sanctum::actingAs($this->member);
        $webhookData = ['url' => 'https://example.com/member-webhook', 'events' => ['task.completed']];
        $this->postJson("/api/v1/projects/{$this->project->id}/webhooks", $webhookData)
             ->assertForbidden();
    }

    public function test_member_cannot_update_webhook_for_project(): void
    {
        $webhook = WebhookSubscription::factory()->for($this->project)->create();
        Sanctum::actingAs($this->member);
        $updatedData = ['url' => 'https://example.com/member-updated'];
        $this->putJson("/api/v1/webhooks/{$webhook->id}", $updatedData)
             ->assertForbidden();
    }

    public function test_member_cannot_delete_webhook_for_project(): void
    {
        $webhook = WebhookSubscription::factory()->for($this->project)->create();
        Sanctum::actingAs($this->member);
        $this->deleteJson("/api/v1/webhooks/{$webhook->id}")
             ->assertForbidden();
    }

    // --- Validation Tests ---

    public function test_create_webhook_fails_with_invalid_data(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/projects/{$this->project->id}/webhooks", [
            'url' => 'invalid-url',
            'events' => ['unknown.event'],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['url', 'events.0']);
    }
}