<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\Auditable;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected array $auditExclude = ['password', 'remember_token', 'email_verified_at'];

    protected function auditLabel(): string
    {
        return (string) $this->name;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function atLeast(UserRole $role): bool
    {
        return $this->role->atLeast($role);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}