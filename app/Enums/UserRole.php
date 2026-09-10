<?php

namespace App\Enums;

/**
 * The three account types of the platform. Allowances live here so the rule has one home.
 */
enum UserRole: string
{
    case Owner = 'owner';
    case Regular = 'regular';
    case Premium = 'premium';

    /**
     * Credits granted on registration and restored on the first day of each month.
     */
    public function monthlyAllowance(): int
    {
        return match ($this) {
            self::Owner => 0,
            self::Regular => 20,
            self::Premium => 40,
        };
    }

    public function canInquire(): bool
    {
        return $this !== self::Owner;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
