<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\Auditable;

class Task extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    // Reordering inside a column changes only `position`, which would flood the log
    protected array $auditExclude = ['position'];

    // project_id is set through the relationship, never from the request
    protected $attributes = [
        'status' => 'todo',
        'priority' => 'medium',
    ];

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
        'due_date',
        'position',
    ];

    protected function auditLabel(): string
    {
        return (string) $this->title;
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_date' => 'date:Y-m-d',
            'position' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}