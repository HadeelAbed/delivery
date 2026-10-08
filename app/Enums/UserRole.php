<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Merchant = 'merchant';
    case Driver = 'driver';
    case Admin = 'admin';
}
