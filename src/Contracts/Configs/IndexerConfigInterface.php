<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts\Configs;

interface IndexerConfigInterface
{
    public function getStoragePath(): string;

    public function getNgramMinSize(): int;

    public function getNgramMaxSize(): int;

    public function isMetaphoneEnabled(): bool;

    public function getDefaultLimit(): int;

    public function isCacheEnabled(): bool;

    public function getCacheTtl(): int;

    public function getBatchSize(): int;

    /** @return array<class-string, string> */
    public function getModelIndexables(): array;

    /** @return array<int, class-string> */
    public function getIndexableModels(): array;
}
