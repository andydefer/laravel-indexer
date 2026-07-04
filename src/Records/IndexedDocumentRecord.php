<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexedDocumentRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?IndexableFingerPrintVO $fingerprint = null,
        public readonly ?ClusterVO $cluster = null,
        public readonly ?StrictAssociative $data = null,
    ) {}
}
