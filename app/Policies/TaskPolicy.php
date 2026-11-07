<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
    public function view(User $user, Task $task): bool
    {
        return true;
    }
    public function create(User $user): bool
    {
        return $user->atLeast(UserRole::Manager);
    }
    public function update(User $user, Task $task): bool
    {
        return $user->atLeast(UserRole::Manager);
    }
    public function updateStatus(User $user, Task $task): bool
    {
        return $user->atLeast(UserRole::Developer);
    }
    public function delete(User $user, Task $task): bool
    {
        return $user->atLeast(UserRole::Manager);
    }
}