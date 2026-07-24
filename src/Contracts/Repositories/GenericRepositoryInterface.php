<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts\Repositories;

use AndyDefer\Repository\AbstractRepositoryInterface;

interface GenericRepositoryInterface extends AbstractRepositoryInterface
{
    public function getActiveChunked(int $chunkSize, callable $callback): void;
}
