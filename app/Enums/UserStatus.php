<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Deactivated = 'deactivated';
}
