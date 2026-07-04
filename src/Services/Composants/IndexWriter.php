<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

final class IndexWriter
{
    public function __construct()
    {
        //
    }

    public function index(Indexable $entity): void
    {
        // TODO: Implémenter la logique d'indexation
    }

    public function indexMany(IndexableRecordCollection $records): void
    {
        // TODO: Implémenter la logique d'indexation multiple
    }
}
