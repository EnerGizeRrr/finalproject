<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'url',
        'events',
        'secret',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'events' => 'array', // Добавляем cast для поля events
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}