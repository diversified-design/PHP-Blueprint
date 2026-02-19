<?php

declare(strict_types=1);

namespace TestFixtures;

/**
 * A backed enum for testing.
 */
enum EnumFixture: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Pending  = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Active   => 'Active',
            self::Inactive => 'Inactive',
            self::Pending  => 'Pending',
        };
    }
}
