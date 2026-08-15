<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Получаем проект из маршрута
        $project = $this->route('project');
        // Разрешаем действие, только если текущий пользователь является владельцем проекта
        return $this->user() && $this->user()->id === $project->owner_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https'],
            'events' => ['required', 'array'],
            'events.*' => ['required', 'string', Rule::in(['task.completed', 'task.status_changed'])],
        ];
    }
}
