<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskStatusRequest extends FormRequest
{
    // app/Http/Requests/UpdateTaskStatusRequest.php
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'position' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }
}
