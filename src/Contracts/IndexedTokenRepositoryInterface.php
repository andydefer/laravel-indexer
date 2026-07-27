<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
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
    public function findByToken(string $token): Collection;

    public function findByType(GramType $type): Collection;

    public function findByField(string $field): Collection;

    public function findByDocumentId(string $documentId): Collection;

    public function findByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): Collection;

    public function findByNamespace(string $namespace): Collection;

    public function findByCluster(ClusterVO $cluster): Collection;

    public function findByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): Collection;

    public function findByClusterKeyValue(string $key, string $value): Collection;

    public function findByTokenAndField(string $token, string $field): Collection;

    public function findByTokenAndType(string $token, GramType $type): Collection;

    public function findByTokenAndNamespace(string $token, string $namespace): Collection;

    public function findByTokenAndCluster(string $token, ClusterVO $cluster): Collection;

    public function findByTokenAndClusters(string $token, ClusterVOCollection $clusters, string $operator = 'AND'): Collection;

    public function findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection;

    public function autocomplete(string $prefix, ?int $limit = 10): Collection;

    public function startingWith(string $letter, ?int $limit = null): Collection;

    public function getDocumentIdsForToken(string $token): Collection;

    public function getDocumentIdsForTokenAndField(string $token, string $field): Collection;

    public function getDocumentIdsForTokenAndCluster(string $token, ClusterVO $cluster): Collection;

    public function getDocumentIdsForTokenAndClusters(string $token, ClusterVOCollection $clusters, string $operator = 'AND'): Collection;

    public function getDocumentIdsForTokenFieldAndCluster(string $token, string $field, ClusterVO $cluster): Collection;

    public function getDocumentIdsForTokenFieldAndClusters(string $token, string $field, ClusterVOCollection $clusters, string $operator = 'AND'): Collection;

    public function countDistinctTokens(): int;

    public function countByType(GramType $type): int;

    public function countByField(string $field): int;

    public function countByNamespace(string $namespace): int;

    public function deleteByDocumentId(string $documentId): int;

    public function deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): int;

    public function deleteByNamespace(string $namespace): int;

    public function deleteByCluster(ClusterVO $cluster): int;

    public function deleteByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): int;

    public function deleteByClusterKeyValue(string $key, string $value): int;

    public function deleteByToken(string $token): int;

    public function deleteByTokenAndField(string $token, string $field): int;

    public function getDistinctTokens(): Collection;

    public function getDistinctFields(): Collection;

    public function findByTokenFieldAndDocument(
        string $token,
        string $field,
        string $documentId,
        GramType $tokenType
    ): ?IndexedToken;

    public function incrementFrequency(string $id): int;

    public function getModel(): Model;
}
