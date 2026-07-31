<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts\Repositories;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Repository interface for indexed document operations.
 *
 * Defines the contract for persistence operations on indexed documents,
 * including CRUD operations and specialized queries for fingerprint,
 * namespace, and cluster-based searches.
 *
 * @extends AbstractRepositoryInterface<IndexedDocument, IndexedDocumentRecord>
 */
interface IndexedDocumentRepositoryInterface extends AbstractRepositoryInterface
{
    // ==================== FIND BY FINGERPRINT ====================

    /**
     * Finds a document by its fingerprint value object.
     *
     * @param  IndexableFingerprintVO  $fingerprint  The fingerprint to search for
     * @return IndexedDocument|null The matching document, or null if not found
     */
    public function findByFingerPrint(IndexableFingerprintVO $fingerprint): ?IndexedDocument;

    /**
     * Finds a document by its raw fingerprint string.
     *
     * @param  string  $fingerprint  The fingerprint string (e.g., 'App\Models\User|123')
     * @return IndexedDocument|null The matching document, or null if not found
     */
    public function findByFingerprintString(string $fingerprint): ?IndexedDocument;

    // ==================== FIND BY NAMESPACE ====================

    /**
     * Finds all documents belonging to a given namespace.
     *
     * @param  string  $namespace  The namespace to filter by (e.g., 'App\Models\User')
     * @return Collection<int, IndexedDocument> A collection of matching documents
     */
    public function findByNamespace(string $namespace): Collection;

    // ==================== FIND BY CLUSTER ====================

    /**
     * Finds documents matching a cluster query expression.
     *
     * The query syntax supports:
     * - Logical operators: & (AND), | (OR)
     * - Grouping with parentheses
     * - SQL functions: COUNT, SUM, AVG, MIN, MAX, LENGTH, EXISTS, HAS, ALL
     * - Sub-conditions: addresses[city=Kinshasa]
     * - Special operators: * (EXISTS), # (NOT_EXISTS)
     *
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @param  DatabaseDriver|null  $driver  The database driver (auto-detected if null)
     * @return Collection<int, IndexedDocument> A collection of matching documents
     *
     * @example
     * // Simple equality
     * $repository->findByClusterQuery('status=active');
     *
     * // AND condition
     * $repository->findByClusterQuery('status=active & role=admin');
     *
     * // OR condition
     * $repository->findByClusterQuery('status=active | status=pending');
     *
     * // SQL function
     * $repository->findByClusterQuery('COUNT(addresses) > 2');
     *
     * // Sub-condition
     * $repository->findByClusterQuery('addresses[city=Kinshasa]');
     */
    public function findByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection;

    // ==================== FIND BY IDS ====================

    /**
     * Finds documents by their primary keys.
     *
     * @param  string[]  $ids  The document IDs to retrieve
     * @return Collection<int, IndexedDocument> A collection of matching documents
     */
    public function findByIds(array $ids): Collection;

    // ==================== DELETE ====================

    /**
     * Deletes a document by its fingerprint value object.
     *
     * @param  IndexableFingerprintVO  $fingerprint  The fingerprint of the document to delete
     * @return int The number of deleted records (0 or 1)
     */
    public function deleteByFingerPrint(IndexableFingerprintVO $fingerprint): int;

    /**
     * Deletes a document by its raw fingerprint string.
     *
     * @param  string  $fingerprint  The fingerprint string of the document to delete
     * @return int The number of deleted records (0 or 1)
     */
    public function deleteByFingerprintString(string $fingerprint): int;

    /**
     * Deletes all documents belonging to a given namespace.
     *
     * @param  string  $namespace  The namespace to delete documents from
     * @return int The number of deleted records
     */
    public function deleteByNamespace(string $namespace): int;

    /**
     * Deletes documents matching a cluster query expression.
     *
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @return int The number of deleted records
     */
    public function deleteByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): int;

    // ==================== COUNT ====================

    /**
     * Counts all documents belonging to a given namespace.
     *
     * @param  string  $namespace  The namespace to count documents for
     * @return int The number of documents
     */
    public function countByNamespace(string $namespace): int;

    /**
     * Counts documents matching a cluster query expression.
     *
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @return int The number of matching documents
     */
    public function countByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): int;

    // ==================== EXISTS ====================

    /**
     * Checks if a document exists by its fingerprint value object.
     *
     * @param  IndexableFingerprintVO  $fingerprint  The fingerprint to check
     * @return bool True if the document exists
     */
    public function existsByFingerPrint(IndexableFingerprintVO $fingerprint): bool;

    /**
     * Checks if any document exists in a given namespace.
     *
     * @param  string  $namespace  The namespace to check
     * @return bool True if at least one document exists
     */
    public function existsByNamespace(string $namespace): bool;

    /**
     * Checks if any document matches a cluster query expression.
     *
     * @param  string  $query  The cluster query expression
     * @param  string  $column  The column containing cluster data
     * @return bool True if at least one document matches
     */
    public function existsByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): bool;

    // ==================== DISTINCT VALUES ====================

    /**
     * Returns all distinct namespaces present in the repository.
     *
     * @return Collection<int, string> A collection of namespace strings
     */
    public function getDistinctNamespaces(): Collection;

    /**
     * Returns all distinct cluster keys present in the repository.
     *
     * @return Collection<int, string> A collection of cluster key strings
     */
    public function getDistinctClusterKeys(): Collection;

    /**
     * Returns all distinct values for a given cluster key.
     *
     * @param  string  $key  The cluster key to retrieve values for
     * @return Collection<int, string> A collection of cluster value strings
     */
    public function getDistinctClusterValues(string $key): Collection;

    // ==================== UTILITY ====================

    /**
     * Returns all documents with their token relationships eager-loaded.
     *
     * This is useful for batch operations that need token data.
     *
     * @return Collection<int, IndexedDocument> A collection of documents with tokens
     */
    public function findAllWithTokens(): Collection;

    /**
     * Returns the underlying Eloquent model instance.
     *
     * @return Model The model instance (IndexedDocument)
     */
    public function getModel(): Model;
}
