<?php

namespace App\Enums;

enum UserRole: string
{
    case Viewer = 'viewer';
    case Developer = 'developer';
    case Manager = 'manager';
    case Admin = 'admin';

    public function level(): int
    {
        return match ($this) {
            self::Viewer => 1,
            self::Developer => 2,
            self::Manager => 3,
            self::Admin => 4,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->level() >= $role->level();
    }
}