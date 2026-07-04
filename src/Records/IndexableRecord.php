<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableEntityIdVO;

/**
 * Record representing an entity to be indexed.
 *
 * @example
 * $record = new IndexableRecord(
 *     id: new IndexableEntityIdVO('123|App.Models.User'),
 *     cluster: new ClusterVO('model-User|tenant-company_123'),
 *     data: StrictAssociative::from(['name' => 'John', 'email' => 'john@example.com'])
 * );
 */
final class IndexableRecord extends AbstractRecord
{
    public function __construct(
        public readonly IndexableEntityIdVO $id,
        public readonly ClusterVO $cluster,
        public readonly StrictAssociative $data,
    ) {}
}
