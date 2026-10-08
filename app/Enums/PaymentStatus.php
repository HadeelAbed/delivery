<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PendingCod = 'pending_cod';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
