<?php

namespace Tests\Feature\Api\V1;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();
        // We don't authenticate user here to be able to test unauthenticated requests.
    }

    private function actingAsUser(): void
    {
        $this->user = $this->user ?? User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_only_user_projects(): void
    {
        $this->actingAsUser();
        Project::factory(2)->create(['owner_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_user_cannot_access_projects_index(): void
    {
        $this->getJson('/api/v1/projects')->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_store_project(): void
    {
        $this->postJson('/api/v1/projects', ['name' => 'test'])->assertUnauthorized();
    }

    public function test_store_is_successful(): void
    {
        $projectData = [
            'name' => 'New Awesome Project',
        ];

        $this->actingAsUser();

        $response = $this->postJson('/api/v1/projects', $projectData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Awesome Project']);

        $this->assertDatabaseHas('projects', [
            'name' => 'New Awesome Project',
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_store_fails_on_validation_error(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/v1/projects', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_show_returns_correct_project(): void
    {
        $this->actingAsUser();
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $this->getJson('/api/v1/projects/' . $project->id)
            ->assertStatus(200)
            ->assertJsonFragment(['name' => $project->name]);
    }

    public function test_show_fails_for_other_user_project(): void
    {
        $this->actingAsUser();
        $anotherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['owner_id' => $anotherUser->id]);

        $this->getJson('/api/v1/projects/' . $otherProject->id)
            ->assertStatus(403);
    }

    public function test_update_is_successful(): void
    {
        $this->actingAsUser();
        $project = Project::factory()->create(['owner_id' => $this->user->id]);
        $updatedData = ['name' => 'Updated Project Name'];

        $response = $this->putJson('/api/v1/projects/' . $project->id, $updatedData);

        $response->assertStatus(200)
            ->assertJsonFragment($updatedData);

        $this->assertDatabaseHas('projects', $updatedData);
    }

    public function test_update_fails_for_other_user_project(): void
    {
        $this->actingAsUser();
        $anotherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['owner_id' => $anotherUser->id]);

        $this->putJson('/api/v1/projects/' . $otherProject->id, ['name' => 'hacker'])
            ->assertStatus(403);
    }

    public function test_update_fails_on_validation_error(): void
    {
        $this->actingAsUser();
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $this->putJson('/api/v1/projects/' . $project->id, ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_destroy_is_successful(): void
    {
        $this->actingAsUser();
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        $this->deleteJson('/api/v1/projects/' . $project->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_destroy_fails_for_other_user_project(): void
    {
        $this->actingAsUser();
        $anotherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['owner_id' => $anotherUser->id]);

        $this->deleteJson('/api/v1/projects/' . $otherProject->id)
            ->assertStatus(403);
    }
}