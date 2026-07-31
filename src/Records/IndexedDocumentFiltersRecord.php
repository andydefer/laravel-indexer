<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

/**
 * Filter record for querying indexed documents.
 *
 * Provides a typed DTO for filtering document queries with various criteria
 * such as ID, fingerprint, namespace, entity ID, and cluster conditions.
 */
final class IndexedDocumentFiltersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?IndexableFingerPrintVO $fingerprint = null,
        public readonly ?string $namespace = null,
        public readonly ?string $entity_id = null,
        public readonly ?string $cluster_query = null,
        public readonly ?StringTypedCollection $document_ids = null,
    ) {}
}
