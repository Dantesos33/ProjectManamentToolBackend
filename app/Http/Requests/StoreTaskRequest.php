<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Models\Task;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;



class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            // `exists` ignores Eloquent scopes, so the tenant check must be explicit
            'assigned_to' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('tenant_id', $this->user()->tenant_id)
            ],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
