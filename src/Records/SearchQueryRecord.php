<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

final class SearchQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchQueryVO $query,
        public readonly ?ClusterQueries $cluster_queries = null,
        public readonly ?int $min_size = null,
        public readonly ?int $max_size = null,
        public readonly ?int $limit = null,
    ) {}
}
