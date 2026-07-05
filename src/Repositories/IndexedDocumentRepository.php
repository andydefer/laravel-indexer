<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Repository for managing indexed documents.
 *
 * Provides CRUD operations and specialized queries for indexed documents,
 * including filtering by fingerprint, namespace, cluster, and bulk operations.
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
    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof IndexedDocumentFiltersRecord) {
            return;
        }

        $this->applyIdFilter($query, $filters);
        $this->applyFingerprintFilter($query, $filters);
        $this->applyNamespaceFilter($query, $filters);
        $this->applyEntityIdFilter($query, $filters);
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
                'cluster' => $record->cluster->value,
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
    public function findByFingerPrint(IndexableFingerPrintVO $fingerPrint): ?IndexedDocument
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerPrint->getValue())
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
        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $namespace.'|%')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByCluster(ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->where('cluster', $cluster->value)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findByClusterKeyValue(string $key, string $value): Collection
    {
        $searchPattern = $key.':'.$value;

        return $this->model->newQuery()
            ->where('cluster', 'LIKE', '%'.$searchPattern.'%')
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
    public function deleteByFingerPrint(IndexableFingerPrintVO $fingerPrint): int
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerPrint->getValue())
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
        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $namespace.'|%')
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByCluster(ClusterVO $cluster): int
    {
        return $this->model->newQuery()
            ->where('cluster', $cluster->value)
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByClusterKeyValue(string $key, string $value): int
    {
        $searchPattern = $key.':'.$value;

        return $this->model->newQuery()
            ->where('cluster', 'LIKE', '%'.$searchPattern.'%')
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function countByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $namespace.'|%')
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function countByCluster(ClusterVO $cluster): int
    {
        return $this->model->newQuery()
            ->where('cluster', $cluster->value)
            ->count();
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
            $parts = explode('|', $document->fingerprint);

            if (isset($parts[0]) && ! $namespaces->contains($parts[0])) {
                $namespaces->add($parts[0]);
            }
        }

        return $namespaces;
    }

    /**
     * {@inheritDoc}
     */
    public function getDistinctClusterKeys(): Collection
    {
        $clusterValues = $this->model->newQuery()
            ->select('cluster')
            ->distinct()
            ->pluck('cluster');

        $keys = collect();

        foreach ($clusterValues as $clusterString) {
            $cluster = new ClusterVO($clusterString);

            foreach (array_keys($cluster->all()) as $key) {
                if (! $keys->contains($key)) {
                    $keys->add($key);
                }
            }
        }

        return $keys;
    }

    /**
     * {@inheritDoc}
     */
    public function getDistinctClusterValues(string $key): Collection
    {
        $clusterValues = $this->model->newQuery()
            ->select('cluster')
            ->distinct()
            ->pluck('cluster');

        $values = collect();

        foreach ($clusterValues as $clusterString) {
            $cluster = new ClusterVO($clusterString);

            if ($cluster->has($key)) {
                foreach ($cluster->get($key) as $value) {
                    if (! $values->contains($value)) {
                        $values->add($value);
                    }
                }
            }
        }

        return $values;
    }

    /**
     * {@inheritDoc}
     */
    public function existsByFingerPrint(IndexableFingerPrintVO $fingerPrint): bool
    {
        return $this->model->newQuery()
            ->where('fingerprint', $fingerPrint->getValue())
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByNamespace(string $namespace): bool
    {
        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $namespace.'|%')
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function existsByCluster(ClusterVO $cluster): bool
    {
        return $this->model->newQuery()
            ->where('cluster', $cluster->value)
            ->exists();
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

    // ============================================================
    // Private Helpers for Filter Application
    // ============================================================

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
            $query->where('fingerprint', 'LIKE', $filters->namespace.'|%');
        }
    }

    private function applyEntityIdFilter(Builder $query, IndexedDocumentFiltersRecord $filters): void
    {
        if ($filters->entity_id !== null) {
            $query->where('fingerprint', 'LIKE', '%|'.$filters->entity_id);
        }
    }

    private function applyClusterFilter(Builder $query, IndexedDocumentFiltersRecord $filters): void
    {
        if ($filters->cluster !== null) {
            $query->where('cluster', $filters->cluster->value);
        }
    }

    private function applyDocumentIdsFilter(Builder $query, IndexedDocumentFiltersRecord $filters): void
    {
        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('id', $filters->document_ids->toArray());
        }
    }
}
