<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\Indexable;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

final class TestIndexableEntityNotIndexable implements Indexable
{
    public function __construct(
        private readonly string $key,
        private readonly string $morphClass,
        private readonly array $data,
        private readonly string $cluster = 'type:test|status:inactive',
    ) {}

    public function shouldBeIndexed(): bool
    {
        return false;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from($this->data);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getMorphClass()
    {
        return $this->morphClass;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return new ClusterVO($this->cluster);
    }
}
