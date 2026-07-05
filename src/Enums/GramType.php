<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Enums;

/**
 * Defines the supported token types for indexing.
 *
 * - LEXICAL: Standard n-gram tokens derived from text
 * - METAPHONE: Phonetic tokens based on the Metaphone algorithm
 */
enum GramType: string
{
    case LEXICAL = 'lexical';
    case METAPHONE = 'metaphone';
}
