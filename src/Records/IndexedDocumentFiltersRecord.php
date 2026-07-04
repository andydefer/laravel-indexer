<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

final class IndexedDocumentFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?string $fingerprint = null,
        public readonly ?string $namespace = null,
        public readonly ?string $entity_id = null,
        public readonly ?ClusterVO $cluster = null,
        public readonly ?StringTypedCollection $document_ids = null,
    ) {}
}
