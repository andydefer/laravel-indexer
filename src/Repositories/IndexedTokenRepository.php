<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelIndexer\Contracts\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class IndexedTokenRepository extends AbstractRepository implements IndexedTokenRepositoryInterface
{
    private ClusterQuery $clusterQuery;

    public function __construct()
    {
        parent::__construct(IndexedToken::class, IndexedTokenRecord::class);
        $this->clusterQuery = new ClusterQuery;
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
        $this->applyDocumentIdsFilter($query, $filters);

        if ($filters->cluster_query !== null) {
            $this->applyClusterQueryOnRelation($query, $filters->cluster_query);
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
        $builder = $this->model->newQuery()->where('token', $token);
        $this->applyClusterQueryOnRelation($builder, $query, $column, $driver);

        return $builder->get();
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
        $builder = $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field);
        $this->applyClusterQueryOnRelation($builder, $query, $column, $driver);

        return $builder->get();
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

    public function findByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection {
        $builder = $this->model->newQuery();
        $this->applyClusterQueryOnRelation($builder, $query, $column, $driver);

        return $builder->get();
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
        $builder = $this->model->newQuery()
            ->where('token', $token)
            ->select('document_id')
            ->distinct();
        $this->applyClusterQueryOnRelation($builder, $query, $column, $driver);

        return $builder->pluck('document_id');
    }

    public function getDocumentIdsForTokenFieldAndClusterQuery(
        string $token,
        string $field,
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection {
        $builder = $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->select('document_id')
            ->distinct();
        $this->applyClusterQueryOnRelation($builder, $query, $column, $driver);

        return $builder->pluck('document_id');
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
        $builder = $this->model->newQuery();
        $this->applyClusterQueryOnRelation($builder, $query, $column, $driver);

        return $builder->count();
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

    public function deleteByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): int {
        $builder = $this->model->newQuery();
        $this->applyClusterQueryOnRelation($builder, $query, $column, $driver);

        return $builder->delete();
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

    // ==================== PRIVATE METHODS ====================

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

    private function applyDocumentIdsFilter(Builder $query, IndexedTokenFiltersRecord $filters): void
    {
        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('document_id', $filters->document_ids->toArray());
        }
    }

    private function applyClusterQueryOnRelation(
        Builder $query,
        string $queryString,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): void {
        if ($driver === null) {
            $driver = $this->detectDriver();
        }

        $query->whereHas('document', function ($subQuery) use ($column, $queryString, $driver) {
            $this->clusterQuery->applyToEloquent(
                $subQuery,
                $column,
                $queryString,
                $driver
            );
        });
    }

    private function detectDriver(): DatabaseDriver
    {
        $driverName = DB::connection()->getDriverName();

        return match ($driverName) {
            'mysql' => DatabaseDriver::MYSQL,
            'pgsql' => DatabaseDriver::PGSQL,
            'sqlite' => DatabaseDriver::SQLITE,
            default => DatabaseDriver::SQLITE,
        };
    }
}
