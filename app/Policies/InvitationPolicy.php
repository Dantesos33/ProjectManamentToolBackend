<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->atLeast(UserRole::Admin);
    }
    public function create(User $actor): bool
    {
        return $actor->atLeast(UserRole::Admin);
    }

    public function delete(User $actor, Invitation $invitation): bool
    {
        return $actor->atLeast(UserRole::Admin) && $actor->tenant_id === $invitation->tenant_id;
    }
}