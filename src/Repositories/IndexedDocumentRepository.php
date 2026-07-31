<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Helpers\ClassHelper;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends AbstractRepository<IndexedDocument, IndexedDocumentRecord>
 */
final class IndexedDocumentRepository extends AbstractRepository implements IndexedDocumentRepositoryInterface
{
    private ClusterQuery $clusterQuery;

    public function __construct()
    {
        parent::__construct(IndexedDocument::class, IndexedDocumentRecord::class);
        $this->clusterQuery = new ClusterQuery;
    }

    // ==================== PUBLIC METHODS ====================

    public function getModel(): Model
    {
        return $this->model;
    }

    public function findByFingerPrint(IndexableFingerPrintVO $fingerPrint): ?IndexedDocument
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerPrint->getValue())
            ->first();
    }

    public function findByFingerprintString(string $fingerprint): ?IndexedDocument
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerprint)
            ->first();
    }

    public function findByNamespace(string $namespace): Collection
    {
        $normalized = ClassHelper::classToDot($namespace);

        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $normalized.'|%')
            ->get();
    }

    public function findByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection {
        $builder = $this->model->newQuery();
        $this->applyClusterQuery($builder, $query, $column, $driver);

        return $builder->get();
    }

    public function findByIds(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return $this->model->newQuery()
            ->whereIn('id', $ids)
            ->get();
    }

    public function deleteByFingerPrint(IndexableFingerPrintVO $fingerPrint): int
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerPrint->getValue())
            ->delete();
    }

    public function deleteByFingerprintString(string $fingerprint): int
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerprint)
            ->delete();
    }

    public function deleteByNamespace(string $namespace): int
    {
        $normalized = ClassHelper::classToDot($namespace);

        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $normalized.'|%')
            ->delete();
    }

    public function deleteByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): int {
        $builder = $this->model->newQuery();
        $this->applyClusterQuery($builder, $query, $column);

        return $builder->delete();
    }

    public function countByNamespace(string $namespace): int
    {
        $normalized = ClassHelper::classToDot($namespace);

        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $normalized.'|%')
            ->count();
    }

    public function countByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): int {
        $builder = $this->model->newQuery();
        $this->applyClusterQuery($builder, $query, $column);

        return $builder->count();
    }

    public function existsByFingerPrint(IndexableFingerPrintVO $fingerPrint): bool
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerPrint->getValue())
            ->exists();
    }

    public function existsByNamespace(string $namespace): bool
    {
        $normalized = ClassHelper::classToDot($namespace);

        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $normalized.'|%')
            ->exists();
    }

    public function existsByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): bool {
        $builder = $this->model->newQuery();
        $this->applyClusterQuery($builder, $query, $column);

        return $builder->exists();
    }

    public function getDistinctNamespaces(): Collection
    {
        $documents = $this->model->newQuery()
            ->select('fingerprint')
            ->get();

        $namespaces = collect();

        foreach ($documents as $document) {
            $parts = explode('|', $document->fingerprint);

            if (isset($parts[0]) && ! $namespaces->contains($parts[0])) {
                $namespaces->add($parts[0]);
            }
        }

        return $namespaces;
    }

    public function getDistinctClusterKeys(): Collection
    {
        $driver = $this->detectDriver();

        $rawKeys = match ($driver) {
            DatabaseDriver::MYSQL => $this->extractKeysFromMySql(),
            DatabaseDriver::PGSQL => $this->extractKeysFromPostgreSql(),
            DatabaseDriver::SQLITE => $this->extractKeysFromSqlite(),
        };

        return $this->normalizeKeys($rawKeys);
    }

    public function getDistinctClusterValues(string $key): Collection
    {
        $driver = $this->detectDriver();

        $rawValues = match ($driver) {
            DatabaseDriver::MYSQL => $this->extractValuesFromMySql($key),
            DatabaseDriver::PGSQL => $this->extractValuesFromPostgreSql($key),
            DatabaseDriver::SQLITE => $this->extractValuesFromSqlite($key),
        };

        return $this->normalizeValues($rawValues);
    }

    public function findAllWithTokens(): Collection
    {
        return $this->model->newQuery()
            ->with('tokens')
            ->get();
    }

    public function createMany(array $records): array
    {
        if (empty($records)) {
            return [];
        }

        $insertData = [];
        $documentIds = [];

        foreach ($records as $record) {
            $id = (string) Str::uuid();
            $documentIds[] = $id;

            $insertData[] = [
                'id' => $id,
                'fingerprint' => $record->fingerprint->getValue(),
                'cluster' => json_encode($record->cluster->toArray()),
                'data' => json_encode($record->data->toArray()),
            ];
        }

        $this->model->newQuery()->insert($insertData);

        return $this->model->newQuery()
            ->whereIn('id', $documentIds)
            ->get()
            ->all();
    }

    // ==================== PROTECTED METHODS ====================

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof IndexedDocumentFiltersRecord) {
            return;
        }

        $this->applyIdFilter($query, $filters);
        $this->applyFingerprintFilter($query, $filters);
        $this->applyNamespaceFilter($query, $filters);
        $this->applyEntityIdFilter($query, $filters);
        $this->applyDocumentIdsFilter($query, $filters);

        if ($filters->cluster_query !== null) {
            $this->applyClusterQuery($query, $filters->cluster_query);
        }
    }

    // ==================== PRIVATE METHODS - FILTERS ====================

    private function applyIdFilter(Builder $query, IndexedDocumentFiltersRecord $filters): void
    {
        if ($filters->id !== null) {
            $query->where('id', $filters->id);
        }
    }

    private function applyFingerprintFilter(Builder $query, IndexedDocumentFiltersRecord $filters): void
    {
        if ($filters->fingerprint !== null) {
            $query->where('fingerprint', $filters->fingerprint);
        }
    }

    private function applyNamespaceFilter(Builder $query, IndexedDocumentFiltersRecord $filters): void
    {
        if ($filters->namespace !== null) {
            $normalized = ClassHelper::classToDot($filters->namespace);
            $query->where('fingerprint', 'LIKE', $normalized.'|%');
        }
    }

    private function applyEntityIdFilter(Builder $query, IndexedDocumentFiltersRecord $filters): void
    {
        if ($filters->entity_id !== null) {
            $query->where('fingerprint', 'LIKE', '%|'.$filters->entity_id);
        }
    }

    private function applyDocumentIdsFilter(Builder $query, IndexedDocumentFiltersRecord $filters): void
    {
        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('id', $filters->document_ids->toArray());
        }
    }

    private function applyClusterQuery(
        Builder $query,
        string $queryString,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): void {
        if ($driver === null) {
            $driver = $this->detectDriver();
        }

        $this->clusterQuery->applyToEloquent(
            $query,
            $column,
            $queryString,
            $driver
        );
    }

    // ==================== PRIVATE METHODS - DISTINCT KEYS ====================

    private function extractKeysFromMySql(): Collection
    {
        $results = $this->model->newQuery()
            ->selectRaw('JSON_KEYS(cluster) as keys')
            ->distinct()
            ->get();

        return $results->pluck('keys');
    }

    private function extractKeysFromPostgreSql(): Collection
    {
        $results = $this->model->newQuery()
            ->selectRaw('jsonb_object_keys(cluster) as keys')
            ->distinct()
            ->get();

        return $results->pluck('keys');
    }

    private function extractKeysFromSqlite(): Collection
    {
        $results = $this->model->newQuery()
            ->select('cluster')
            ->distinct()
            ->get();

        return $results
            ->pluck('cluster')
            ->map(fn (string $json) => $this->safeDecodeJson($json))
            ->filter(fn (?array $data) => $data !== null)
            ->flatMap(fn (array $data) => array_keys($data));
    }

    private function normalizeKeys(Collection $rawKeys): Collection
    {
        return $rawKeys
            ->flatMap(fn ($keys) => $this->decodeJsonKeys($keys))
            ->filter()
            ->unique()
            ->values();
    }

    private function decodeJsonKeys(mixed $keys): array
    {
        if (is_array($keys)) {
            return $keys;
        }

        if (is_string($keys)) {
            $decoded = json_decode($keys, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    // ==================== PRIVATE METHODS - DISTINCT VALUES ====================

    private function extractValuesFromMySql(string $key): Collection
    {
        $results = $this->model->newQuery()
            ->selectRaw("JSON_EXTRACT(cluster, '$.\"{$key}\"') as value")
            ->distinct()
            ->whereRaw("JSON_EXTRACT(cluster, '$.\"{$key}\"') IS NOT NULL")
            ->get();

        return $results->pluck('value');
    }

    private function extractValuesFromPostgreSql(string $key): Collection
    {
        $results = $this->model->newQuery()
            ->selectRaw("cluster->>'{$key}' as value")
            ->distinct()
            ->whereRaw("cluster->>'{$key}' IS NOT NULL")
            ->get();

        return $results->pluck('value');
    }

    private function extractValuesFromSqlite(string $key): Collection
    {
        $results = $this->model->newQuery()
            ->select('cluster')
            ->distinct()
            ->get();

        return $results
            ->pluck('cluster')
            ->map(fn (string $json) => $this->safeDecodeJson($json))
            ->filter(fn (?array $data) => $data !== null && isset($data[$key]))
            ->map(fn (array $data) => $data[$key]);
    }

    private function normalizeValues(Collection $rawValues): Collection
    {
        return $rawValues
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => $this->normalizeValue($value))
            ->unique()
            ->values();
    }

    private function normalizeValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value),
            is_null($value) => '',
            default => (string) $value,
        };
    }

    // ==================== PRIVATE METHODS - UTILITY ====================

    private function safeDecodeJson(string $json): ?array
    {
        $data = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    private function detectDriver(): DatabaseDriver
    {
        $driverName = DB::connection()->getDriverName();

        return match ($driverName) {
            'mysql' => DatabaseDriver::MYSQL,
            'pgsql' => DatabaseDriver::PGSQL,
            'sqlite' => DatabaseDriver::SQLITE,
            default => throw new \RuntimeException(
                sprintf('Unsupported database driver: "%s". Supported drivers: mysql, pgsql, sqlite', $driverName)
            ),
        };
    }
}
