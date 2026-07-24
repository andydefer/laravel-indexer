<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;

interface GenericIndexerInterface
{
    public function setBatchSize(int $batchSize): self;

    public function index(IndexableVO $indexableVO, int $id): void;

    public function indexAll(IndexableVO $indexableVO): void;

    public function reindexAll(IndexableVO $indexableVO): void;

    public function delete(IndexableVO $indexableVO, int $id): void;

    public function deleteAll(IndexableVO $indexableVO): void;

    public function refresh(IndexableVO $indexableVO, int $id): void;

    public function countIndexed(IndexableVO $indexableVO): int;

    public function exists(IndexableVO $indexableVO, int $id): bool;
}
