<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Entrepreneur = 'entrepreneur';
    case Staff = 'staff';
    case Client = 'client';
}
