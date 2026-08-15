<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'idempotency_key',
        'webhook_subscription_id',
        'status',
        'status_code',
        'response_time',
        'error',
        'response_body',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the subscription that owns the attempt.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}