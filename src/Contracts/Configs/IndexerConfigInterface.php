<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts\Configs;

/**
 * Configuration interface for the indexer service.
 *
 * Defines the contract for accessing all configurable settings of the
 * indexing system, including n-gram generation, storage paths, caching,
 * and batch processing parameters.
 */
interface IndexerConfigInterface
{
    /**
     * Returns the filesystem path where index data should be stored.
     *
     * @return string The storage directory path
     */
    public function getStoragePath(): string;

    /**
     * Returns the minimum n-gram size for text tokenization.
     *
     * N-grams shorter than this value will not be generated.
     *
     * @return int The minimum n-gram length (>= 1)
     */
    public function getNgramMinSize(): int;

    /**
     * Returns the maximum n-gram size for text tokenization.
     *
     * N-grams longer than this value will not be generated.
     *
     * @return int The maximum n-gram length
     */
    public function getNgramMaxSize(): int;

    /**
     * Determines whether metaphone phonetic tokenization is enabled.
     *
     * When enabled, metaphone n-grams are generated alongside lexical n-grams
     * to improve fuzzy and phonetic search capabilities.
     *
     * @return bool True if metaphone tokenization is active
     */
    public function isMetaphoneEnabled(): bool;

    /**
     * Returns the default maximum number of results returned by search queries.
     *
     * @return int The default result limit
     */
    public function getDefaultLimit(): int;

    /**
     * Determines whether search result caching is enabled.
     *
     * @return bool True if caching is active
     */
    public function isCacheEnabled(): bool;

    /**
     * Returns the Time-To-Live for cached search results, in seconds.
     *
     * @return int Cache TTL in seconds
     */
    public function getCacheTtl(): int;

    /**
     * Returns the number of documents processed in each batch during indexing operations.
     *
     * @return int The batch size
     */
    public function getBatchSize(): int;

    /**
     * Returns the list of model classes that should be automatically indexed.
     *
     * The returned array maps model class names to their configuration.
     *
     * @return array<class-string, mixed> An associative array of model configurations
     */
    public function getModelIndexables(): array;

    /**
     * Returns the maximum length of a text chunk for full-text indexing.
     *
     * Texts longer than this value are split into smaller chunks during indexing.
     *
     * @return int The maximum chunk length in characters
     */
    public function getFullTextMaxLength(): int;

    /**
     * Returns the maximum length of a single text value that will be indexed.
     *
     * Values longer than this limit are truncated before indexing.
     *
     * @return int The maximum text length in characters
     */
    public function getMaxTextLength(): int;
}
