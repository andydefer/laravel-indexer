<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Enums;

enum GramType: string
{
    case LEXICAL = 'lexical';
    case METAPHONE = 'metaphone';
}
