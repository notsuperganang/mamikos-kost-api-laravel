<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case InitialGrant = 'initial_grant';
    case AvailabilityInquiry = 'availability_inquiry';
    case MonthlyRecharge = 'monthly_recharge';
}
