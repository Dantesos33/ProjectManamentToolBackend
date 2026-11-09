<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->atLeast(UserRole::Admin);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->atLeast(UserRole::Admin) && $actor->tenant_id === $target->tenant_id;
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }
}