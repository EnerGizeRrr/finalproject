<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WebhookSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'url' => $this->faker->url(),
            'events' => $this->faker->randomElements(['task.completed', 'task.status_changed'], $this->faker->numberBetween(1, 2)),
            'secret' => Str::random(32),
            'enabled' => true,
        ];
    }
}
