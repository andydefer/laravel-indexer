<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

final class IndexedDocumentRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $fingerprint = null,
        public readonly ?StrictAssociative $cluster = null,
        public readonly ?StrictAssociative $data = null,
    ) {}
}
