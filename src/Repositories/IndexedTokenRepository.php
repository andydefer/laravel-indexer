<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\Contracts\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\Services\Composants\ClusterFilterApplier;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class IndexedTokenRepository extends AbstractRepository implements IndexedTokenRepositoryInterface
{
    private ClusterFilterApplier $clusterFilterApplier;

    public function __construct()
    {
        parent::__construct(IndexedToken::class, IndexedTokenRecord::class);
        $this->clusterFilterApplier = new ClusterFilterApplier;
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof IndexedTokenFiltersRecord) {
            return;
        }

        $this->applyIdFilter($query, $filters);
        $this->applyTokenFilter($query, $filters);
        $this->applyTokenTypeFilter($query, $filters);
        $this->applyFieldFilter($query, $filters);
        $this->applyNamespaceFilter($query, $filters);
        $this->applyClusterFilter($query, $filters);
        $this->applyDocumentIdsFilter($query, $filters);
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function findByToken(string $token): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->get();
    }

    public function findByType(GramType $type): Collection
    {
        return $this->model->newQuery()
            ->where('token_type', $type)
            ->get();
    }

    public function findByField(string $field): Collection
    {
        return $this->model->newQuery()
            ->where('field', $field)
            ->get();
    }

    public function findByDocumentId(string $documentId): Collection
    {
        return $this->model->newQuery()
            ->where('document_id', $documentId)
            ->get();
    }

    public function findByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($fingerPrint): void {
                $query->where('fingerprint', $fingerPrint->getValue());
            })
            ->get();
    }

    public function findByNamespace(string $namespace): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->get();
    }

    public function findByCluster(ClusterVO $cluster): Collection
    {
        $clusters = new ClusterVOCollection;
        $clusters->add($cluster);

        return $this->findByClusters($clusters, $cluster->getMode() ?? 'AND');
    }

    public function findByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): Collection
    {
        $query = $this->model->newQuery();
        $this->clusterFilterApplier->applyClustersOnRelation($query, $clusters, $operator);

        return $query->get();
    }

    public function findByClusterKeyValue(string $key, string $value): Collection
    {
        $searchPattern = $key.':'.$value;

        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($searchPattern): void {
                $query->where('cluster', 'LIKE', '%'.$searchPattern.'%');
            })
            ->get();
    }

    public function findByTokenAndField(string $token, string $field): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->get();
    }

    public function findByTokenAndType(string $token, GramType $type): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('token_type', $type)
            ->get();
    }

    public function findByTokenAndNamespace(string $token, string $namespace): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->get();
    }

    public function findByTokenAndCluster(string $token, ClusterVO $cluster): Collection
    {
        $clusters = new ClusterVOCollection;
        $clusters->add($cluster);

        return $this->findByTokenAndClusters($token, $clusters, $cluster->getMode() ?? 'AND');
    }

    public function findByTokenAndClusters(string $token, ClusterVOCollection $clusters, string $operator = 'AND'): Collection
    {
        $query = $this->model->newQuery()
            ->where('token', $token);

        $this->clusterFilterApplier->applyClustersOnRelation($query, $clusters, $operator);

        return $query->get();
    }

    public function findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->get();
    }

    public function autocomplete(string $prefix, ?int $limit = 10): Collection
    {
        $query = $this->model->newQuery()
            ->where('token', 'LIKE', $prefix.'%')
            ->select('token')
            ->distinct();

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function startingWith(string $letter, ?int $limit = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('token', 'LIKE', $letter.'%');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getDocumentIdsForToken(string $token): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->select('document_id')
            ->distinct()
            ->pluck('document_id');
    }

    public function getDocumentIdsForTokenAndField(string $token, string $field): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->select('document_id')
            ->distinct()
            ->pluck('document_id');
    }

    public function getDocumentIdsForTokenAndCluster(string $token, ClusterVO $cluster): Collection
    {
        $clusters = new ClusterVOCollection;
        $clusters->add($cluster);

        return $this->getDocumentIdsForTokenAndClusters($token, $clusters, $cluster->getMode() ?? 'AND');
    }

    public function getDocumentIdsForTokenAndClusters(string $token, ClusterVOCollection $clusters, string $operator = 'AND'): Collection
    {
        $query = $this->model->newQuery()
            ->where('token', $token)
            ->select('document_id')
            ->distinct();

        $this->clusterFilterApplier->applyClustersOnRelation($query, $clusters, $operator);

        return $query->pluck('document_id');
    }

    public function getDocumentIdsForTokenFieldAndCluster(string $token, string $field, ClusterVO $cluster): Collection
    {
        $clusters = new ClusterVOCollection;
        $clusters->add($cluster);

        return $this->getDocumentIdsForTokenFieldAndClusters($token, $field, $clusters, $cluster->getMode() ?? 'AND');
    }

    public function getDocumentIdsForTokenFieldAndClusters(string $token, string $field, ClusterVOCollection $clusters, string $operator = 'AND'): Collection
    {
        $query = $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->select('document_id')
            ->distinct();

        $this->clusterFilterApplier->applyClustersOnRelation($query, $clusters, $operator);

        return $query->pluck('document_id');
    }

    public function findByTokenFieldAndDocument(
        string $token,
        string $field,
        string $documentId,
        GramType $tokenType
    ): ?IndexedToken {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->where('document_id', $documentId)
            ->where('token_type', $tokenType)
            ->first();
    }

    public function countDistinctTokens(): int
    {
        return $this->model->newQuery()
            ->distinct('token')
            ->count('token');
    }

    public function countByType(GramType $type): int
    {
        return $this->model->newQuery()
            ->where('token_type', $type)
            ->count();
    }

    public function countByField(string $field): int
    {
        return $this->model->newQuery()
            ->where('field', $field)
            ->count();
    }

    public function countByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->count();
    }

    public function deleteByDocumentId(string $documentId): int
    {
        return $this->model->newQuery()
            ->where('document_id', $documentId)
            ->delete();
    }

    public function deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($fingerPrint): void {
                $query->where('fingerprint', $fingerPrint->getValue());
            })
            ->delete();
    }

    public function deleteByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->delete();
    }

    public function deleteByCluster(ClusterVO $cluster): int
    {
        $clusters = new ClusterVOCollection;
        $clusters->add($cluster);

        return $this->deleteByClusters($clusters, $cluster->getMode() ?? 'AND');
    }

    public function deleteByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): int
    {
        $query = $this->model->newQuery();
        $this->clusterFilterApplier->applyClustersOnRelation($query, $clusters, $operator);

        return $query->delete();
    }

    public function deleteByClusterKeyValue(string $key, string $value): int
    {
        $searchPattern = $key.':'.$value;

        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($searchPattern): void {
                $query->where('cluster', 'LIKE', '%'.$searchPattern.'%');
            })
            ->delete();
    }

    public function deleteByToken(string $token): int
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->delete();
    }

    public function deleteByTokenAndField(string $token, string $field): int
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->delete();
    }

    public function getDistinctTokens(): Collection
    {
        return $this->model->newQuery()
            ->select('token')
            ->distinct()
            ->pluck('token');
    }

    public function getDistinctFields(): Collection
    {
        return $this->model->newQuery()
            ->whereNotNull('field')
            ->select('field')
            ->distinct()
            ->pluck('field');
    }

    public function incrementFrequency(string $id): int
    {
        return $this->model->newQuery()
            ->where('id', $id)
            ->increment('frequency');
    }

    private function applyIdFilter(Builder $query, IndexedTokenFiltersRecord $filters): void
    {
        if ($filters->id !== null) {
            $query->where('id', $filters->id);
        }
    }

    private function applyTokenFilter(Builder $query, IndexedTokenFiltersRecord $filters): void
    {
        if ($filters->token !== null) {
            $query->where('token', $filters->token);
        }
    }

    private function applyTokenTypeFilter(Builder $query, IndexedTokenFiltersRecord $filters): void
    {
        if ($filters->token_type !== null) {
            $query->where('token_type', $filters->token_type);
        }
    }

    private function applyFieldFilter(Builder $query, IndexedTokenFiltersRecord $filters): void
    {
        if ($filters->field !== null) {
            $query->where('field', $filters->field);
        }
    }

    private function applyNamespaceFilter(Builder $query, IndexedTokenFiltersRecord $filters): void
    {
        if ($filters->namespace !== null) {
            $query->whereHas('document', function (Builder $query) use ($filters): void {
                $query->where('fingerprint', 'LIKE', $filters->namespace.'|%');
            });
        }
    }

    private function applyClusterFilter(Builder $query, IndexedTokenFiltersRecord $filters): void
    {
        if ($filters->cluster_key !== null && $filters->cluster_value !== null) {
            $searchPattern = $filters->cluster_key.':'.$filters->cluster_value;

            $query->whereHas('document', function (Builder $query) use ($searchPattern): void {
                $query->where('cluster', 'LIKE', '%'.$searchPattern.'%');
            });
        }
    }

    private function applyDocumentIdsFilter(Builder $query, IndexedTokenFiltersRecord $filters): void
    {
        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('document_id', $filters->document_ids->toArray());
        }
    }
}
