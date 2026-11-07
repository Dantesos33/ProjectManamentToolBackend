<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    // app/Http/Requests/UpdateTaskRequest.php
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'assigned_to' => [
                'sometimes',
                'nullable',
                Rule::exists('users', 'id')
                    ->where('tenant_id', $this->user()->tenant_id)
            ],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
