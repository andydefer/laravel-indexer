<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexableRecord extends AbstractRecord
{
    public function __construct(
        public readonly IndexableFingerPrintVO $finger_print,
        public readonly StrictAssociative $data,
        public readonly ?ClusterVO $cluster = null,
    ) {}
}
