<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\Auditable;

class Project extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    // tenant_id is deliberately NOT fillable: the trait sets it
    protected $fillable = ['name', 'description', 'created_by'];

    protected function auditLabel(): string
    {
        return (string) $this->name;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}