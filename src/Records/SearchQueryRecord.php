<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

final class SearchQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchQueryVO $query,
        public readonly ?ClusterVOCollection $clusters = null,
        public readonly ?int $min_size = null,
        public readonly ?int $max_size = null,
        public readonly ?int $limit = null,
    ) {}
}
