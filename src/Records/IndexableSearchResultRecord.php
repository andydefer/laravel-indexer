<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\Enums\GramType;

/**
 * Record représentant un résultat de recherche.
 */
final class IndexableSearchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly IndexableRecord $item,
        public readonly string $field,
        public readonly string $gram_value,
        public readonly GramType $gram_type,
    ) {}
}
