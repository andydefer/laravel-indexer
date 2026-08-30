<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelIndexer\Contracts\Repositories\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Repository implementation for IndexedDocument models.
 *
 * @extends AbstractRepository<IndexedDocument, IndexedDocumentRecord>
 */
final class IndexedDocumentRepository extends AbstractRepository implements IndexedDocumentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(IndexedDocument::class, IndexedDocumentRecord::class);
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
    public function findByFingerPrint(IndexableFingerprintVO $fingerprint): ?IndexedDocument
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerprint->getValue())
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByFingerprintString(string $fingerprint): ?IndexedDocument
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerprint)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByNamespace(string $namespace): Collection
    {
        $escapedNamespace = $this->escapeNamespace($namespace);

        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $escapedNamespace.'|%')
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
            ->whereCluster($column, $query)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByIds(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return $this->model->newQuery()
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByFingerPrint(IndexableFingerprintVO $fingerprint): int
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerprint->getValue())
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByFingerprintString(string $fingerprint): int
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerprint)
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByNamespace(string $namespace): int
    {
        $escapedNamespace = $this->escapeNamespace($namespace);

        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $escapedNamespace.'|%')
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): int {
        return $this->model->newQuery()
            ->whereCluster($column, $query)
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function countByNamespace(string $namespace): int
    {
        $escapedNamespace = $this->escapeNamespace($namespace);

        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $escapedNamespace.'|%')
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function countByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): int {
        return $this->model->newQuery()
            ->whereCluster($column, $query)
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByFingerPrint(IndexableFingerprintVO $fingerprint): bool
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerprint->getValue())
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByNamespace(string $namespace): bool
    {
        $escapedNamespace = $this->escapeNamespace($namespace);

        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $escapedNamespace.'|%')
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): bool {
        return $this->model->newQuery()
            ->whereCluster($column, $query)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function getDistinctNamespaces(): Collection
    {
        $documents = $this->model->newQuery()
            ->select('fingerprint')
            ->get();

        $namespaces = collect();

        foreach ($documents as $document) {
            $namespace = $document->fingerprint->getNamespace();

            if (! $namespaces->contains($namespace)) {
                $namespaces->add($namespace);
            }
        }

        return $namespaces;
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function findAllWithTokens(): Collection
    {
        return $this->model->newQuery()
            ->with('tokens')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof IndexedDocumentFiltersRecord) {
            return;
        }

        if ($filters->id !== null) {
            $query->where('id', $filters->id);
        }

        if ($filters->fingerprint !== null) {
            $query->where('fingerprint', $filters->fingerprint->getValue());
        }

        if ($filters->namespace !== null) {
            $escapedNamespace = $this->escapeNamespace($filters->namespace);
            $query->where('fingerprint', 'LIKE', $escapedNamespace.'|%');
        }

        if ($filters->entity_id !== null) {
            $query->where('fingerprint', 'LIKE', '%|'.$filters->entity_id);
        }

        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('id', $filters->document_ids->toArray());
        }

        if ($filters->cluster_query !== null) {
            $query->whereCluster('cluster', $filters->cluster_query);
        }
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

    /**
     * Échappe les backslashes dans un namespace pour les requêtes SQL LIKE.
     * MySQL uniquement car le backslash est un caractère d'échappement dans LIKE.
     * SQLite et PostgreSQL n'ont pas besoin d'échappement.
     *
     * @param  string  $namespace  Le namespace à échapper
     * @return string Le namespace échappé
     */
    private function escapeNamespace(string $namespace): string
    {
        $driverName = DB::connection()->getDriverName();

        // MySQL uniquement (backslash est un caractère d'échappement)
        if ($driverName === 'mysql') {
            return str_replace('\\', '\\\\', $namespace);
        }

        // SQLite, PostgreSQL, etc. n'ont pas besoin d'échappement
        return $namespace;
    }
}
