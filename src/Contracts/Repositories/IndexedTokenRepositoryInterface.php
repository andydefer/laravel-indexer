<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts\Repositories;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
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
    // ==================== FIND BY TOKEN ====================

    public function findByToken(string $token): Collection;

    public function findByTokenAndField(string $token, string $field): Collection;

    public function findByTokenAndType(string $token, GramType $type): Collection;

    public function findByTokenAndNamespace(string $token, string $namespace): Collection;

    /**
     * Find tokens by token value and cluster query.
     *
     * @param  string  $token  The token value
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @param  DatabaseDriver|null  $driver  The database driver
     * @return Collection<IndexedToken>
     */
    public function findByTokenAndClusterQuery(
        string $token,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection;

    public function findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection;

    /**
     * Find tokens by token, field and cluster query.
     *
     * @param  string  $token  The token value
     * @param  string  $field  The field name
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @param  DatabaseDriver|null  $driver  The database driver
     * @return Collection<IndexedToken>
     */
    public function findByTokenFieldAndClusterQuery(
        string $token,
        string $field,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection;

    public function findByTokenFieldAndDocument(
        string $token,
        string $field,
        string $documentId,
        GramType $tokenType
    ): ?IndexedToken;

    // ==================== FIND BY TYPE ====================

    public function findByType(GramType $type): Collection;

    public function findByField(string $field): Collection;

    // ==================== FIND BY DOCUMENT ====================

    public function findByDocumentId(string $documentId): Collection;

    public function findByDocumentFingerPrint(IndexableFingerprintVO $fingerprint): Collection;

    public function findByNamespace(string $namespace): Collection;

    // ==================== FIND BY CLUSTER ====================

    /**
     * Find tokens matching a cluster query expression.
     *
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @param  DatabaseDriver|null  $driver  The database driver
     * @return Collection<IndexedToken>
     */
    public function findByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection;

    // ==================== AUTOCOMPLETE ====================

    public function autocomplete(string $prefix, ?int $limit = 10): Collection;

    public function startingWith(string $letter, ?int $limit = null): Collection;

    // ==================== DOCUMENT IDS ====================

    public function getDocumentIdsForToken(string $token): Collection;

    public function getDocumentIdsForTokenAndField(string $token, string $field): Collection;

    /**
     * Get document IDs for a token matching a cluster query.
     *
     * @param  string  $token  The token value
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @param  DatabaseDriver|null  $driver  The database driver
     * @return Collection<string> Document IDs
     */
    public function getDocumentIdsForTokenAndClusterQuery(
        string $token,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection;

    /**
     * Get document IDs for a token, field and cluster query.
     *
     * @param  string  $token  The token value
     * @param  string  $field  The field name
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @param  DatabaseDriver|null  $driver  The database driver
     * @return Collection<string> Document IDs
     */
    public function getDocumentIdsForTokenFieldAndClusterQuery(
        string $token,
        string $field,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection;

    // ==================== COUNT ====================

    public function countDistinctTokens(): int;

    public function countByType(GramType $type): int;

    public function countByField(string $field): int;

    public function countByNamespace(string $namespace): int;

    /**
     * Count tokens matching a cluster query expression.
     *
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @param  DatabaseDriver|null  $driver  The database driver
     * @return int Number of matching tokens
     */
    public function countByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): int;

    // ==================== DELETE ====================

    public function deleteByDocumentId(string $documentId): int;

    public function deleteByDocumentFingerPrint(IndexableFingerprintVO $fingerprint): int;

    public function deleteByNamespace(string $namespace): int;

    /**
     * Delete tokens matching a cluster query expression.
     *
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @param  DatabaseDriver|null  $driver  The database driver
     * @return int Number of deleted tokens
     */
    public function deleteByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): int;

    public function deleteByToken(string $token): int;

    public function deleteByTokenAndField(string $token, string $field): int;

    // ==================== DISTINCT ====================

    public function getDistinctTokens(): Collection;

    public function getDistinctFields(): Collection;

    // ==================== UTILITY ====================

    public function incrementFrequency(string $id): int;

    public function getModel(): Model;
}
