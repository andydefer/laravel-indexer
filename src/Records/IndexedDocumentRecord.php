<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

/**
 * Record representing an indexed document.
 *
 * A data transfer object that holds all the data required to persist
 * or transfer an indexed document, including its fingerprint,
 * cluster data, and the document content.
 */
final class IndexedDocumentRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?IndexableFingerPrintVO $fingerprint = null,
        public readonly ?ClusterVO $cluster = null,
        public readonly ?StrictAssociative $data = null,
    ) {}
}
