<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Для гибкости при PUT/PATCH - все поля, которые можно обновить, являются опциональными, если не переданы.
        // Мы используем 'sometimes' для всех полей, кроме тех, которые не должны меняться (project_id).
        $optionalRule = 'sometimes';

        return [
            'project_id' => [$optionalRule, 'exists:projects,id'], // project_id не меняется при обновлении задачи
            'title' => [$optionalRule, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => [$optionalRule, Rule::enum(TaskStatus::class)],
            'priority' => [$optionalRule, Rule::enum(TaskPriority::class)],
            'due_date' => [$optionalRule, 'date'],
        ];
    }
}
