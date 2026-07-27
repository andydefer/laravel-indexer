<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case BANNED = 'banned';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }
}
