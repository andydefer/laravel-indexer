<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\Enums;

enum Language
{
    case FR;
    case EN;
    case LU;
    case LN;
    case SW;
    case DE;

    public function getCode(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::FR => 'Français',
            self::EN => 'English',
            self::LU => 'Lingala',
            self::LN => 'Lingala',
            self::SW => 'Swahili',
            self::DE => 'Deutsch',
        };
    }
}
