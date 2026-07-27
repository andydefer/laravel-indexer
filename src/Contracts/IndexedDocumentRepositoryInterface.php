<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
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
    public function findByFingerPrint(IndexableFingerPrintVO $fingerPrint): ?IndexedDocument;

    public function findByFingerprintString(string $fingerprint): ?IndexedDocument;

    public function findByNamespace(string $namespace): Collection;

    public function findByCluster(ClusterVO $cluster): Collection;

    public function findByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): Collection;

    public function findByClusterKeyValue(string $key, string $value): Collection;

    public function findByIds(array $ids): Collection;

    public function deleteByFingerPrint(IndexableFingerPrintVO $fingerPrint): int;

    public function deleteByFingerprintString(string $fingerprint): int;

    public function deleteByNamespace(string $namespace): int;

    public function deleteByCluster(ClusterVO $cluster): int;

    public function deleteByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): int;

    public function deleteByClusterKeyValue(string $key, string $value): int;

    public function countByNamespace(string $namespace): int;

    public function countByCluster(ClusterVO $cluster): int;

    public function countByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): int;

    public function getDistinctNamespaces(): Collection;

    public function getDistinctClusterKeys(): Collection;

    public function getDistinctClusterValues(string $key): Collection;

    public function existsByFingerPrint(IndexableFingerPrintVO $fingerPrint): bool;

    public function existsByNamespace(string $namespace): bool;

    public function existsByCluster(ClusterVO $cluster): bool;

    public function existsByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): bool;

    public function findAllWithTokens(): Collection;

    public function getModel(): Model;
}
