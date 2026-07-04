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
use Illuminate\Support\Collection;

final class IndexedDocumentRepository extends AbstractRepository implements IndexedDocumentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(IndexedDocument::class, IndexedDocumentRecord::class);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof IndexedDocumentFiltersRecord) {
            return;
        }

        if ($filters->id !== null) {
            $query->where('id', $filters->id);
        }

        if ($filters->fingerprint !== null) {
            $query->where('fingerprint', $filters->fingerprint);
        }

        if ($filters->namespace !== null) {
            $query->where('fingerprint', 'LIKE', $filters->namespace.'|%');
        }

        if ($filters->entity_id !== null) {
            $query->where('fingerprint', 'LIKE', '%|'.$filters->entity_id);
        }

        if ($filters->cluster !== null) {
            $query->where('cluster', $filters->cluster->value);
        }

        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('id', $filters->document_ids->toArray());
        }
    }

    public function findByFingerPrint(IndexableFingerPrintVO $fingerPrint): ?IndexedDocument
    {
        return $this->model->newQuery()->where('fingerprint', $fingerPrint->getValue())->first();
    }

    public function findByFingerprintString(string $fingerprint): ?IndexedDocument
    {
        return $this->model->newQuery()->where('fingerprint', $fingerprint)->first();
    }

    public function findByNamespace(string $namespace): Collection
    {
        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $namespace.'|%')
            ->get();
    }

    public function findByCluster(ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->where('cluster', $cluster->value)
            ->get();
    }

    public function findByClusterKeyValue(string $key, string $value): Collection
    {
        $search = $key.':'.$value;

        return $this->model->newQuery()
            ->where('cluster', 'LIKE', '%'.$search.'%')
            ->get();
    }

    public function findByIds(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return $this->model->newQuery()->whereIn('id', $ids)->get();
    }

    public function deleteByFingerPrint(IndexableFingerPrintVO $fingerPrint): int
    {
        return $this->model->newQuery()->where('fingerprint', $fingerPrint->getValue())->delete();
    }

    public function deleteByFingerprintString(string $fingerprint): int
    {
        return $this->model->newQuery()->where('fingerprint', $fingerprint)->delete();
    }

    public function deleteByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $namespace.'|%')
            ->delete();
    }

    public function deleteByCluster(ClusterVO $cluster): int
    {
        return $this->model->newQuery()
            ->where('cluster', $cluster->value)
            ->delete();
    }

    public function deleteByClusterKeyValue(string $key, string $value): int
    {
        $search = $key.':'.$value;

        return $this->model->newQuery()
            ->where('cluster', 'LIKE', '%'.$search.'%')
            ->delete();
    }

    public function countByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $namespace.'|%')
            ->count();
    }

    public function countByCluster(ClusterVO $cluster): int
    {
        return $this->model->newQuery()
            ->where('cluster', $cluster->value)
            ->count();
    }

    public function getDistinctNamespaces(): Collection
    {
        $documents = $this->model->newQuery()
            ->select('fingerprint')
            ->get();

        $namespaces = collect();
        foreach ($documents as $doc) {
            $parts = explode('|', $doc->fingerprint);
            if (isset($parts[0]) && ! $namespaces->contains($parts[0])) {
                $namespaces->add($parts[0]);
            }
        }

        return $namespaces;
    }

    public function getDistinctClusterKeys(): Collection
    {
        $clusters = $this->model->newQuery()
            ->select('cluster')
            ->distinct()
            ->pluck('cluster');

        $keys = collect();
        foreach ($clusters as $cluster) {
            $vo = new ClusterVO($cluster);
            foreach (array_keys($vo->all()) as $key) {
                if (! $keys->contains($key)) {
                    $keys->add($key);
                }
            }
        }

        return $keys;
    }

    public function getDistinctClusterValues(string $key): Collection
    {
        $clusters = $this->model->newQuery()
            ->select('cluster')
            ->distinct()
            ->pluck('cluster');

        $values = collect();
        foreach ($clusters as $cluster) {
            $vo = new ClusterVO($cluster);
            if ($vo->has($key)) {
                $value = $vo->get($key);
                // $value est un array, on ajoute chaque valeur individuellement
                foreach ($value as $val) {
                    if (! $values->contains($val)) {
                        $values->add($val);
                    }
                }
            }
        }

        return $values;
    }

    public function existsByFingerPrint(IndexableFingerPrintVO $fingerPrint): bool
    {
        return $this->model->newQuery()->where('fingerprint', $fingerPrint->getValue())->exists();
    }

    public function existsByNamespace(string $namespace): bool
    {
        return $this->model->newQuery()
            ->where('fingerprint', 'LIKE', $namespace.'|%')
            ->exists();
    }

    public function existsByCluster(ClusterVO $cluster): bool
    {
        return $this->model->newQuery()
            ->where('cluster', $cluster->value)
            ->exists();
    }

    public function findAllWithTokens(): Collection
    {
        return $this->model->newQuery()->with('tokens')->get();
    }
}
