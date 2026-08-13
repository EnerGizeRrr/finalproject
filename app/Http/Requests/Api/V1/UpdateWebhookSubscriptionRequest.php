<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebhookSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Получаем подписку из маршрута (благодаря shallow-маршрутизации)
        $webhook = $this->route('webhook');

        // Если веб-хук не найден в маршруте, это не запрос на обновление,
        // поэтому мы не должны блокировать его на этом уровне.
        if (!$webhook) {
            return true;
        }

        // Разрешаем действие, только если пользователь является владельцем проекта, к которому относится веб-хук
        return $webhook instanceof \App\Models\WebhookSubscription && $webhook->project && $this->user() && $this->user()->id === $webhook->project->owner_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'required', 'url:http,https'],
            'events' => ['sometimes', 'required', 'array'],
            'events.*' => ['required_with:events', 'string', Rule::in(['task.completed', 'task.status_changed'])],
            'enabled' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
