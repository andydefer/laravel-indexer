<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\Enums\GramType;

final class IndexedTokenFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $token = null,
        public readonly ?GramType $token_type = null,
        public readonly ?string $field = null,
        public readonly ?string $namespace = null,
        public readonly ?string $cluster_key = null,
        public readonly ?string $cluster_value = null,
        public readonly ?StringTypedCollection $document_ids = null,
    ) {}
}
