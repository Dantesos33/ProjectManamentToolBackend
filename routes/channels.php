<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}', function (User $user, string $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});