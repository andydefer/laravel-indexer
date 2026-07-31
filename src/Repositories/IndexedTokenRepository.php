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

final class IndexedTokenRepository extends AbstractRepository implements IndexedTokenRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(IndexedToken::class, IndexedTokenRecord::class);
    }

    /**
     * {@inheritDoc}
     * Surcharge pour appliquer les filtres cluster sur la relation document.
     */
    public function findBy(FindByRecord $record): Collection
    {
        $query = $this->buildQuery($record->filters);

        // Appliquer les cluster queries sur la relation document
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
     * Surcharge pour appliquer les filtres cluster sur la relation document.
     */
    public function paginate(PaginateRecord $record): LengthAwarePaginator
    {
        $query = $this->buildQuery($record->filters);

        // Appliquer les cluster queries sur la relation document
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

    public function findByDocumentFingerPrint(IndexableFingerPrintVO $fingerprint): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($fingerprint): void {
                $query->where('fingerprint', $fingerprint->getValue());
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

    public function deleteByDocumentId(string $documentId): int
    {
        return $this->model->newQuery()
            ->where('document_id', $documentId)
            ->delete();
    }

    public function deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerprint): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function (Builder $query) use ($fingerprint): void {
                $query->where('fingerprint', $fingerprint->getValue());
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
}
