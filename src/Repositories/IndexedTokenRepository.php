<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\Contracts\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Repository for managing indexed tokens.
 *
 * Provides CRUD operations and specialized queries for tokens,
 * including filtering by token value, type, field, namespace, and cluster.
 *
 * @extends AbstractRepository<IndexedToken, IndexedTokenRecord>
 */
final class IndexedTokenRepository extends AbstractRepository implements IndexedTokenRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(IndexedToken::class, IndexedTokenRecord::class);
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * {@inheritDoc}
     */
    public function findByToken(string $token): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByType(GramType $type): Collection
    {
        return $this->model->newQuery()
            ->where('token_type', $type)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByField(string $field): Collection
    {
        return $this->model->newQuery()
            ->where('field', $field)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByDocumentId(string $documentId): Collection
    {
        return $this->model->newQuery()
            ->where('document_id', $documentId)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($fingerPrint): void {
                $query->where('fingerprint', $fingerPrint->getValue());
            })
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByNamespace(string $namespace): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByCluster(ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($cluster): void {
                $query->where('cluster', $cluster->value);
            })
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByClusterKeyValue(string $key, string $value): Collection
    {
        $searchPattern = $key.':'.$value;

        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($searchPattern): void {
                $query->where('cluster', 'LIKE', '%'.$searchPattern.'%');
            })
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByTokenAndField(string $token, string $field): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByTokenAndType(string $token, GramType $type): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('token_type', $type)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByTokenAndNamespace(string $token, string $namespace): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByTokenAndCluster(string $token, ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->whereHas('document', function (Builder $query) use ($cluster): void {
                $query->where('cluster', $cluster->value);
            })
            ->get();
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function startingWith(string $letter, ?int $limit = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('token', 'LIKE', $letter.'%');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    public function getDocumentIdsForToken(string $token): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->select('document_id')
            ->distinct()
            ->pluck('document_id');
    }

    /**
     * {@inheritDoc}
     */
    public function getDocumentIdsForTokenAndField(string $token, string $field): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->select('document_id')
            ->distinct()
            ->pluck('document_id');
    }

    /**
     * {@inheritDoc}
     */
    public function getDocumentIdsForTokenAndCluster(string $token, ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->whereHas('document', function (Builder $query) use ($cluster): void {
                $query->where('cluster', $cluster->value);
            })
            ->select('document_id')
            ->distinct()
            ->pluck('document_id');
    }

    /**
     * {@inheritDoc}
     */
    public function getDocumentIdsForTokenFieldAndCluster(string $token, string $field, ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->whereHas('document', function (Builder $query) use ($cluster): void {
                $query->where('cluster', $cluster->value);
            })
            ->select('document_id')
            ->distinct()
            ->pluck('document_id');
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function countDistinctTokens(): int
    {
        return $this->model->newQuery()
            ->distinct('token')
            ->count('token');
    }

    /**
     * {@inheritDoc}
     */
    public function countByType(GramType $type): int
    {
        return $this->model->newQuery()
            ->where('token_type', $type)
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function countByField(string $field): int
    {
        return $this->model->newQuery()
            ->where('field', $field)
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function countByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByDocumentId(string $documentId): int
    {
        return $this->model->newQuery()
            ->where('document_id', $documentId)
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($fingerPrint): void {
                $query->where('fingerprint', $fingerPrint->getValue());
            })
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($namespace): void {
                $query->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByCluster(ClusterVO $cluster): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($cluster): void {
                $query->where('cluster', $cluster->value);
            })
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByClusterKeyValue(string $key, string $value): int
    {
        $searchPattern = $key.':'.$value;

        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($searchPattern): void {
                $query->where('cluster', 'LIKE', '%'.$searchPattern.'%');
            })
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByToken(string $token): int
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByTokenAndField(string $token, string $field): int
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function getDistinctTokens(): Collection
    {
        return $this->model->newQuery()
            ->select('token')
            ->distinct()
            ->pluck('token');
    }

    /**
     * {@inheritDoc}
     */
    public function getDistinctFields(): Collection
    {
        return $this->model->newQuery()
            ->whereNotNull('field')
            ->select('field')
            ->distinct()
            ->pluck('field');
    }

    /**
     * {@inheritDoc}
     */
    public function incrementFrequency(string $id): int
    {
        return $this->model->newQuery()
            ->where('id', $id)
            ->increment('frequency');
    }

    // ============================================================
    // Private Helpers for Filter Application
    // ============================================================

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
