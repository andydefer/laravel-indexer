<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Repository interface for indexed token operations.
 *
 * @extends AbstractRepositoryInterface<IndexedToken, IndexedTokenRecord>
 */
interface IndexedTokenRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Finds tokens by their value.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByToken(string $token): Collection;

    /**
     * Finds tokens by their type.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByType(GramType $type): Collection;

    /**
     * Finds tokens by their field name.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByField(string $field): Collection;

    /**
     * Finds tokens belonging to a document.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByDocumentId(string $documentId): Collection;

    /**
     * Finds tokens belonging to a document identified by fingerprint.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): Collection;

    /**
     * Finds tokens belonging to a namespace.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByNamespace(string $namespace): Collection;

    /**
     * Finds tokens matching a cluster.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByCluster(ClusterVO $cluster): Collection;

    /**
     * Finds tokens matching a cluster key-value pair.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByClusterKeyValue(string $key, string $value): Collection;

    /**
     * Finds tokens by both token value and field.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByTokenAndField(string $token, string $field): Collection;

    /**
     * Finds tokens by both token value and type.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByTokenAndType(string $token, GramType $type): Collection;

    /**
     * Finds tokens by token value and namespace.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByTokenAndNamespace(string $token, string $namespace): Collection;

    /**
     * Finds tokens by token value and cluster.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByTokenAndCluster(string $token, ClusterVO $cluster): Collection;

    /**
     * Finds tokens by token value, field, and namespace.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection;

    /**
     * Performs autocomplete suggestions based on a token prefix.
     *
     * @return Collection<int, IndexedToken> Collection of distinct token suggestions
     */
    public function autocomplete(string $prefix, ?int $limit = 10): Collection;

    /**
     * Finds tokens starting with a specific letter.
     *
     * @return Collection<int, IndexedToken> Collection of tokens
     */
    public function startingWith(string $letter, ?int $limit = null): Collection;

    /**
     * Retrieves document IDs for a given token.
     *
     * @return Collection<int, string> Collection of document UUIDs
     */
    public function getDocumentIdsForToken(string $token): Collection;

    /**
     * Retrieves document IDs for a given token and field.
     *
     * @return Collection<int, string> Collection of document UUIDs
     */
    public function getDocumentIdsForTokenAndField(string $token, string $field): Collection;

    /**
     * Retrieves document IDs for a given token and cluster.
     *
     * @return Collection<int, string> Collection of document UUIDs
     */
    public function getDocumentIdsForTokenAndCluster(string $token, ClusterVO $cluster): Collection;

    /**
     * Retrieves document IDs for a given token, field, and cluster.
     *
     * @return Collection<int, string> Collection of document UUIDs
     */
    public function getDocumentIdsForTokenFieldAndCluster(string $token, string $field, ClusterVO $cluster): Collection;

    /**
     * Counts the total number of distinct token values.
     *
     * @return int Number of distinct tokens
     */
    public function countDistinctTokens(): int;

    /**
     * Counts tokens by type.
     *
     * @return int Number of tokens
     */
    public function countByType(GramType $type): int;

    /**
     * Counts tokens by field.
     *
     * @return int Number of tokens
     */
    public function countByField(string $field): int;

    /**
     * Counts tokens by namespace.
     *
     * @return int Number of tokens
     */
    public function countByNamespace(string $namespace): int;

    /**
     * Deletes all tokens belonging to a document.
     *
     * @return int Number of deleted tokens
     */
    public function deleteByDocumentId(string $documentId): int;

    /**
     * Deletes tokens belonging to a document identified by fingerprint.
     *
     * @return int Number of deleted tokens
     */
    public function deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): int;

    /**
     * Deletes tokens belonging to a namespace.
     *
     * @return int Number of deleted tokens
     */
    public function deleteByNamespace(string $namespace): int;

    /**
     * Deletes tokens matching a cluster.
     *
     * @return int Number of deleted tokens
     */
    public function deleteByCluster(ClusterVO $cluster): int;

    /**
     * Deletes tokens matching a cluster key-value pair.
     *
     * @return int Number of deleted tokens
     */
    public function deleteByClusterKeyValue(string $key, string $value): int;

    /**
     * Deletes tokens by their value.
     *
     * @return int Number of deleted tokens
     */
    public function deleteByToken(string $token): int;

    /**
     * Deletes tokens by their value and field.
     *
     * @return int Number of deleted tokens
     */
    public function deleteByTokenAndField(string $token, string $field): int;

    /**
     * Returns all distinct token values.
     *
     * @return Collection<int, string> List of unique token values
     */
    public function getDistinctTokens(): Collection;

    /**
     * Returns all distinct field names.
     *
     * @return Collection<int, string> List of unique field names
     */
    public function getDistinctFields(): Collection;

    /**
     * Finds a token by its token value, field, document, and type.
     *
     * @return IndexedToken|null The found token, or null if not found
     */
    public function findByTokenFieldAndDocument(
        string $token,
        string $field,
        string $documentId,
        GramType $tokenType
    ): ?IndexedToken;

    /**
     * Increments the frequency counter of a token by its ID.
     *
     * @return int The new frequency value
     */
    public function incrementFrequency(string $id): int;

    /**
     * Returns the underlying Eloquent model instance.
     *
     * @return Model The Eloquent model
     */
    public function getModel(): Model;
}
