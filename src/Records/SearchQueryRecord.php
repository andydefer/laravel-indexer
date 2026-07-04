<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

/**
 * Record représentant une requête de recherche.
 *
 * @example
 * $query = new SearchQueryRecord(
 *     query: new SearchQueryVO('john=name,description|doe=name'),
 *     finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
 *     cluster: new ClusterVO('model-User|tenant-company_abc|env-production'),
 *     limit: 100,
 * );
 */
final class SearchQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchQueryVO $query,
        public readonly ?IndexableFingerPrintVO $finger_print = null,
        public readonly ?ClusterVO $cluster = null,
        public readonly ?int $limit = null,
    ) {}
}
