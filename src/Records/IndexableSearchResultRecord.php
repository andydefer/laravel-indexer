<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\Enums\GramType;

/**
 * Record representing a single search result.
 *
 * Contains the matched document, the field that was matched,
 * the gram value that produced the match, and the gram type.
 */
final class IndexableSearchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly IndexedDocumentRecord $item,
        public readonly string $field,
        public readonly string $gram_value,
        public readonly GramType $gram_type,
    ) {}
}
