<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Helpers;

final class ClassHelper
{
    public static function classToDot(string $className): string
    {
        return str_replace('\\', '.', $className);
    }
}
