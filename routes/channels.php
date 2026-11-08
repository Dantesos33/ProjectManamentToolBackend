<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Everyone in the tenant: task and project events
Broadcast::channel('tenant.{tenantId}', function (User $user, string $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Admins only: audit log events
Broadcast::channel('tenant.{tenantId}.admin', function (User $user, string $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId && $user->role === UserRole::Admin;
});