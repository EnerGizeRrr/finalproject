<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWebhookSubscriptionRequest;
use App\Http\Requests\Api\V1\UpdateWebhookSubscriptionRequest;
use App\Http\Resources\Api\V1\WebhookSubscriptionResource;
use App\Models\Project;
use App\Models\WebhookSubscription;
use Illuminate\Support\Str;

class WebhookSubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $subscriptions = $project->webhookSubscriptions()->paginate(10);

        return WebhookSubscriptionResource::collection($subscriptions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWebhookSubscriptionRequest $request, Project $project)
    {
        $validatedData = $request->validated();

        $subscription = $project->webhookSubscriptions()->create([
            'url' => $validatedData['url'],
            'events' => $validatedData['events'],
            'secret' => Str::random(32), // Генерируем случайный секрет
        ]);

        // Возвращаем ресурс с секретом только при создании
        return response()->json([
            'data' => WebhookSubscriptionResource::make($subscription),
            'secret' => $subscription->secret,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(WebhookSubscription $webhook)
    {
        $this->authorize('view', $webhook->project);

        return WebhookSubscriptionResource::make($webhook);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWebhookSubscriptionRequest $request, WebhookSubscription $webhook)
    {
        $webhook->update($request->validated());

        return WebhookSubscriptionResource::make($webhook);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WebhookSubscription $webhook)
    {
        $this->authorize('delete', $webhook->project);

        $webhook->delete();

        return response()->noContent();
    }
}
