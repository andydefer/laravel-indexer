<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Configs;

use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class IndexerConfig implements IndexerConfigInterface
{
    private const DEFAULT_MIN_NGRAM_SIZE = 2;

    private const DEFAULT_MAX_NGRAM_SIZE = 4;

    private const DEFAULT_LIMIT = 100;

    private const DEFAULT_CACHE_TTL = 3600;

    private const DEFAULT_BATCH_SIZE = 50;

    private const DEFAULT_MODEL_INDEXABLES = [];

    public function __construct(
        private readonly ConfigRepository $config
    ) {}

    public function getStoragePath(): string
    {
        return $this->config->get('indexer.storage_path', storage_path('indexer'));
    }

    public function getNgramMinSize(): int
    {
        return (int) $this->config->get('indexer.token_types.ngrams.min_size', self::DEFAULT_MIN_NGRAM_SIZE);
    }

    public function getNgramMaxSize(): int
    {
        return (int) $this->config->get('indexer.token_types.ngrams.max_size', self::DEFAULT_MAX_NGRAM_SIZE);
    }

    public function isMetaphoneEnabled(): bool
    {
        return (bool) $this->config->get('indexer.token_types.metaphone', true);
    }

    public function getDefaultLimit(): int
    {
        return (int) $this->config->get('indexer.default_limit', self::DEFAULT_LIMIT);
    }

    public function isCacheEnabled(): bool
    {
        return (bool) $this->config->get('indexer.enable_cache', true);
    }

    public function getCacheTtl(): int
    {
        return (int) $this->config->get('indexer.cache_ttl', self::DEFAULT_CACHE_TTL);
    }

    public function getBatchSize(): int
    {
        return (int) $this->config->get('indexer.batch_size', self::DEFAULT_BATCH_SIZE);
    }

    public function getModelIndexables(): array
    {
        $models = $this->config->get('indexer.model_indexables', self::DEFAULT_MODEL_INDEXABLES);

        return is_array($models) ? $models : self::DEFAULT_MODEL_INDEXABLES;
    }

    public function getIndexableModels(): array
    {
        return array_keys($this->getModelIndexables());
    }
}
