<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Fixtures\Indexable;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

final class TestIndexableEntity implements Indexable
{
    public function __construct(
        private readonly string $key,
        private readonly string $morphClass,
        private readonly array $data,
        private readonly array $cluster = ['model' => 'default', 'tenant' => 'default'],
    ) {}

    public function shouldBeIndexed(): bool
    {
        return true;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from($this->data);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getMorphClass(): string
    {
        return $this->morphClass;
    }

    public function getIndexableCluster(): array
    {
        return $this->cluster;
    }
}
