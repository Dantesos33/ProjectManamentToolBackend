<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn(Model $m) => $m->recordAudit(
            'created',
            null,
            Arr::except($m->getAttributes(), $m->auditExcluded()),
        ));

        static::updated(function (Model $m) {
            // getChanges() holds what was just saved, getRawOriginal() what was there before
            $new = Arr::except($m->getChanges(), $m->auditExcluded());
            if (!$new) {
                return;   // only noise fields changed (e.g. a reorder)
            }

            $m->recordAudit('updated', Arr::only($m->getRawOriginal(), array_keys($new)), $new);
        });

        static::deleted(fn(Model $m) => $m->recordAudit(
            'deleted',
            Arr::except($m->getAttributes(), $m->auditExcluded()),
            null,
        ));
    }

    /** Fields never written to the log */
    public function auditExcluded(): array
    {
        $extra = property_exists($this, 'auditExclude') ? $this->auditExclude : [];

        return array_merge(['id', 'created_at', 'updated_at'], $extra);
    }

    /** Human name shown in descriptions. Models override this. */
    protected function auditLabel(): string
    {
        return '#' . $this->getKey();
    }

    protected function recordAudit(string $event, ?array $old, ?array $new): void
    {
        // Only changes made by a signed-in user are recorded.
        // Seeders, console commands and queued jobs are skipped on purpose.
        if (!auth()->check()) {
            return;
        }

        $entity = strtolower(class_basename($this));

        AuditLog::create([
            'tenant_id' => $this->tenant_id,
            'user_id' => auth()->id(),
            'action' => "{$entity}.{$event}",
            'auditable_type' => $entity,
            'auditable_id' => $this->getKey(),
            'description' => $this->auditDescription($entity, $event, $old ?? [], $new ?? []),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
        ]);
    }

    protected function auditDescription(string $entity, string $event, array $old, array $new): string
    {
        $label = "{$entity} \"{$this->auditLabel()}\"";

        return match ($event) {
            'created' => "Created {$label}",
            'deleted' => "Deleted {$label}",
            default => "Updated {$label}: " . $this->auditSummary($old, $new),
        };
    }

    protected function auditSummary(array $old, array $new): string
    {
        $parts = [];
        foreach (array_slice($new, 0, 3, true) as $field => $value) {
            $parts[] = "{$field} " . $this->auditFormat($old[$field] ?? null) . ' → ' . $this->auditFormat($value);
        }

        $more = count($new) - 3;

        return implode(', ', $parts) . ($more > 0 ? " (+{$more} more)" : '');
    }

    protected function auditFormat(mixed $value): string
    {
        return ($value === null || $value === '') ? 'none' : Str::limit((string) $value, 40);
    }
}