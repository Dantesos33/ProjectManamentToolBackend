<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'entity' => ['nullable', Rule::in(['task', 'project', 'user', 'invitation'])],
            'event' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'user_id' => ['nullable', 'integer'],
        ]);

        $logs = AuditLog::with('user:id,name')
            ->when(
                ($filters['entity'] ?? null) || ($filters['event'] ?? null),
                fn($q) => $q->where('action', 'like', ($filters['entity'] ?? '%') . '.' . ($filters['event'] ?? '%')),
            )
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->latest('id')   // newest first
            ->paginate(25);

        return AuditLogResource::collection($logs);
    }
}