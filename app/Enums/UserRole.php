<?php

namespace App\Enums;

enum UserRole: string
{
    case Viewer = 'viewer';
    case Developer = 'developer';
    case Manager = 'manager';
    case Admin = 'admin';
}
