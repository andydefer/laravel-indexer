<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelIndexer\Contracts\Repositories\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepository;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Repository implementation for IndexedToken models.
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
    public function findBy(FindByRecord $record): Collection
    {
        $query = $this->buildQuery($record->filters);

        if ($record->clusterQueries !== null) {
            $query->whereHas('document', function ($subQuery) use ($record) {
                foreach ($record->clusterQueries->all() as $column => $queryExpression) {
                    $subQuery->whereCluster($column, $queryExpression);
                }
            });
        }

        $this->applySelectColumns($query, $record);
        $this->applySorting($query, $record);
        $this->applyLimit($query, $record);

        /** @var Collection<int, IndexedToken> $result */
        $result = $query->get();

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function paginate(PaginateRecord $record): LengthAwarePaginator
    {
        $query = $this->buildQuery($record->filters);

        if ($record->clusterQueries !== null) {
            $query->whereHas('document', function ($subQuery) use ($record) {
                foreach ($record->clusterQueries->all() as $column => $queryExpression) {
                    $subQuery->whereCluster($column, $queryExpression);
                }
            });
        }

        if ($record->sortBy !== null) {
            $query->orderBy($record->sortBy, $record->sortDir->toSql());
        }

        /** @var LengthAwarePaginator<IndexedToken> $result */
        $result = $query->paginate(
            perPage: $record->perPage,
            columns: $record->columns->toArray(),
            pageName: 'page',
            page: $record->page
        );

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof IndexedTokenFiltersRecord) {
            return;
        }

        if ($filters->id !== null) {
            $query->where('id', $filters->id);
        }

        if ($filters->token !== null) {
            $query->where('token', $filters->token);
        }

        if ($filters->token_type !== null) {
            $query->where('token_type', $filters->token_type);
        }

        if ($filters->field !== null) {
            $query->where('field', $filters->field);
        }

        if ($filters->namespace !== null) {
            $query->whereHas('document', function (Builder $subQuery) use ($filters): void {
                $subQuery->where('fingerprint', 'LIKE', $filters->namespace.'|%');
            });
        }

        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('document_id', $filters->document_ids->toArray());
        }

        if ($filters->cluster_query !== null) {
            $query->whereHas('document', function ($subQuery) use ($filters): void {
                $subQuery->whereCluster('cluster', $filters->cluster_query);
            });
        }
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
    public function findByTokenAndClusterQuery(
        string $token,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection {
        return $this->model->newQuery()
            ->where('token', $token)
            ->whereHas('document', function ($subQuery) use ($column, $query): void {
                $subQuery->whereCluster($column, $query);
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
    public function findByTokenFieldAndClusterQuery(
        string $token,
        string $field,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->whereHas('document', function ($subQuery) use ($column, $query): void {
                $subQuery->whereCluster($column, $query);
            })
            ->get();
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
    public function findByDocumentFingerPrint(IndexableFingerPrintVO $fingerprint): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($fingerprint): void {
                $query->where('fingerprint', $fingerprint->getValue());
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
    public function findByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection {
        return $this->model->newQuery()
            ->whereHas('document', function ($subQuery) use ($column, $query): void {
                $subQuery->whereCluster($column, $query);
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
    public function getDocumentIdsForTokenAndClusterQuery(
        string $token,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection {
        return $this->model->newQuery()
            ->where('token', $token)
            ->select('document_id')
            ->distinct()
            ->whereHas('document', function ($subQuery) use ($column, $query): void {
                $subQuery->whereCluster($column, $query);
            })
            ->pluck('document_id');
    }

    /**
     * {@inheritDoc}
     */
    public function getDocumentIdsForTokenFieldAndClusterQuery(
        string $token,
        string $field,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->select('document_id')
            ->distinct()
            ->whereHas('document', function ($subQuery) use ($column, $query): void {
                $subQuery->whereCluster($column, $query);
            })
            ->pluck('document_id');
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
    public function countByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): int {
        return $this->model->newQuery()
            ->whereHas('document', function ($subQuery) use ($column, $query): void {
                $subQuery->whereCluster($column, $query);
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
    public function deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerprint): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($fingerprint): void {
                $query->where('fingerprint', $fingerprint->getValue());
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
    public function deleteByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): int {
        return $this->model->newQuery()
            ->whereHas('document', function ($subQuery) use ($column, $query): void {
                $subQuery->whereCluster($column, $query);
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
}
