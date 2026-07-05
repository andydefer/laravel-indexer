<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Repository interface for indexed document operations.
 *
 * @extends AbstractRepositoryInterface<IndexedDocument, IndexedDocumentRecord>
 */
interface IndexedDocumentRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Finds a document by its fingerprint value object.
     *
     * @return IndexedDocument|null The found document, or null if not found
     */
    public function findByFingerPrint(IndexableFingerPrintVO $fingerPrint): ?IndexedDocument;

    /**
     * Finds a document by its raw fingerprint string.
     *
     * @return IndexedDocument|null The found document, or null if not found
     */
    public function findByFingerprintString(string $fingerprint): ?IndexedDocument;

    /**
     * Finds all documents belonging to a namespace.
     *
     * @return Collection<int, IndexedDocument> Collection of documents
     */
    public function findByNamespace(string $namespace): Collection;

    /**
     * Finds all documents matching a cluster.
     *
     * @return Collection<int, IndexedDocument> Collection of documents
     */
    public function findByCluster(ClusterVO $cluster): Collection;

    /**
     * Finds all documents matching a cluster key-value pair.
     *
     * @return Collection<int, IndexedDocument> Collection of documents
     */
    public function findByClusterKeyValue(string $key, string $value): Collection;

    /**
     * Finds documents by their IDs.
     *
     * @param  array<string>  $ids  List of document UUIDs
     * @return Collection<int, IndexedDocument> Collection of documents
     */
    public function findByIds(array $ids): Collection;

    /**
     * Deletes a document by its fingerprint.
     *
     * @return int Number of deleted records (0 or 1)
     */
    public function deleteByFingerPrint(IndexableFingerPrintVO $fingerPrint): int;

    /**
     * Deletes a document by its raw fingerprint string.
     *
     * @return int Number of deleted records (0 or 1)
     */
    public function deleteByFingerprintString(string $fingerprint): int;

    /**
     * Deletes all documents belonging to a namespace.
     *
     * @return int Number of deleted records
     */
    public function deleteByNamespace(string $namespace): int;

    /**
     * Deletes all documents matching a cluster.
     *
     * @return int Number of deleted records
     */
    public function deleteByCluster(ClusterVO $cluster): int;

    /**
     * Deletes all documents matching a cluster key-value pair.
     *
     * @return int Number of deleted records
     */
    public function deleteByClusterKeyValue(string $key, string $value): int;

    /**
     * Counts documents belonging to a namespace.
     *
     * @return int Number of documents
     */
    public function countByNamespace(string $namespace): int;

    /**
     * Counts documents matching a cluster.
     *
     * @return int Number of documents
     */
    public function countByCluster(ClusterVO $cluster): int;

    /**
     * Returns all distinct namespaces.
     *
     * @return Collection<int, string> List of unique namespaces
     */
    public function getDistinctNamespaces(): Collection;

    /**
     * Returns all distinct cluster keys.
     *
     * @return Collection<int, string> List of unique cluster keys
     */
    public function getDistinctClusterKeys(): Collection;

    /**
     * Returns all distinct cluster values for a given key.
     *
     * @return Collection<int, string> List of unique cluster values
     */
    public function getDistinctClusterValues(string $key): Collection;

    /**
     * Checks if a document exists by fingerprint.
     *
     * @return bool True if the document exists, false otherwise
     */
    public function existsByFingerPrint(IndexableFingerPrintVO $fingerPrint): bool;

    /**
     * Checks if any document exists in a namespace.
     *
     * @return bool True if at least one document exists, false otherwise
     */
    public function existsByNamespace(string $namespace): bool;

    /**
     * Checks if any document exists matching a cluster.
     *
     * @return bool True if at least one document exists, false otherwise
     */
    public function existsByCluster(ClusterVO $cluster): bool;

    /**
     * Returns all documents with their related tokens eagerly loaded.
     *
     * @return Collection<int, IndexedDocument> Collection of documents with tokens
     */
    public function findAllWithTokens(): Collection;

    /**
     * Returns the underlying Eloquent model instance.
     *
     * @return Model The Eloquent model
     */
    public function getModel(): Model;
}
