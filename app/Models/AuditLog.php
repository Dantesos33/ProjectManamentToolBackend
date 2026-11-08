<?php

namespace App\Models;

use App\Events\AuditLogged;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToTenant;   // reads are filtered to the current tenant

    const UPDATED_AT = null;   // entries are written once and never change

    protected $guarded = [];   // only ever created by the Auditable trait, never from a request

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    protected static function booted(): void
    {
        // Append-only: refuse edits and deletes through Eloquent
        static::updating(fn() => false);
        static::deleting(fn() => false);

        static::created(function (AuditLog $log) {
            try {
                event(new AuditLogged($log->tenant_id, $log->id));
            } catch (\Throwable $e) {
                report($e);   // a failed broadcast must never break the action being audited
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}