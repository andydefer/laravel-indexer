<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\Enums\GramType;

final class IndexedTokenRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $document_id = null,
        public readonly ?GramType $token_type = null,
        public readonly ?string $token = null,
        public readonly ?string $field = null,
    ) {}
}
