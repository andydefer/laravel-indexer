<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\Enums;

enum UserType: string
{
    case PATIENT = 'patient';
    case DOCTOR = 'doctor';
    case ADMIN = 'admin';
    case STAFF = 'staff';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::PATIENT => 'Patient',
            self::DOCTOR => 'Doctor',
            self::ADMIN => 'Administrator',
            self::STAFF => 'Staff',
        };
    }

    public function isDoctor(): bool
    {
        return $this === self::DOCTOR;
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
}
