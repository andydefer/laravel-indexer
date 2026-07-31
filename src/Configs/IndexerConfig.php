<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Configs;

use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Configuration manager for the indexer service.
 *
 * Provides access to all configurable settings for the indexing system,
 * including n-gram sizes, storage paths, caching, and batch processing.
 *
 * All values are read from the Laravel configuration repository with
 * sensible defaults applied when values are not explicitly set.
 */
final class IndexerConfig implements IndexerConfigInterface
{
    private const DEFAULT_MIN_NGRAM_SIZE = 2;

    private const DEFAULT_MAX_NGRAM_SIZE = 4;

    private const DEFAULT_LIMIT = 100;

    private const DEFAULT_CACHE_TTL = 3600;

    private const DEFAULT_BATCH_SIZE = 50;

    private const DEFAULT_MODEL_INDEXABLES = [];

    private const DEFAULT_FULL_TEXT_MAX_LENGTH = 100;

    private const DEFAULT_MAX_TEXT_LENGTH = 1000;

    public function __construct(
        private readonly ConfigRepository $config
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getStoragePath(): string
    {
        return $this->config->get(
            'indexer.storage_path',
            storage_path('indexer')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getNgramMinSize(): int
    {
        return (int) $this->config->get(
            'indexer.token_types.ngrams.min_size',
            self::DEFAULT_MIN_NGRAM_SIZE
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getNgramMaxSize(): int
    {
        return (int) $this->config->get(
            'indexer.token_types.ngrams.max_size',
            self::DEFAULT_MAX_NGRAM_SIZE
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isMetaphoneEnabled(): bool
    {
        return (bool) $this->config->get(
            'indexer.token_types.metaphone',
            true
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultLimit(): int
    {
        return (int) $this->config->get(
            'indexer.default_limit',
            self::DEFAULT_LIMIT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isCacheEnabled(): bool
    {
        return (bool) $this->config->get(
            'indexer.enable_cache',
            true
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheTtl(): int
    {
        return (int) $this->config->get(
            'indexer.cache_ttl',
            self::DEFAULT_CACHE_TTL
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getBatchSize(): int
    {
        return (int) $this->config->get(
            'indexer.batch_size',
            self::DEFAULT_BATCH_SIZE
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getModelIndexables(): array
    {
        $models = $this->config->get(
            'indexer.model_indexables',
            self::DEFAULT_MODEL_INDEXABLES
        );

        return is_array($models) ? $models : self::DEFAULT_MODEL_INDEXABLES;
    }

    /**
     * {@inheritDoc}
     */
    public function getFullTextMaxLength(): int
    {
        return (int) $this->config->get(
            'indexer.full_text_max_length',
            self::DEFAULT_FULL_TEXT_MAX_LENGTH
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxTextLength(): int
    {
        return (int) $this->config->get(
            'indexer.max_text_length',
            self::DEFAULT_MAX_TEXT_LENGTH
        );
    }
}
