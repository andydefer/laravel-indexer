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

final class IndexedTokenRepository extends AbstractRepository implements IndexedTokenRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(IndexedToken::class, IndexedTokenRecord::class);
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
            $query->whereHas('document', function ($q) use ($filters) {
                $q->where('fingerprint', 'LIKE', $filters->namespace.'|%');
            });
        }

        if ($filters->cluster_key !== null && $filters->cluster_value !== null) {
            $search = $filters->cluster_key.':'.$filters->cluster_value;
            $query->whereHas('document', function ($q) use ($search) {
                $q->where('cluster', 'LIKE', '%'.$search.'%');
            });
        }

        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('document_id', $filters->document_ids->toArray());
        }
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function findByToken(string $token): Collection
    {
        return $this->model->newQuery()->where('token', $token)->get();
    }

    public function findByType(GramType $type): Collection
    {
        return $this->model->newQuery()->where('token_type', $type)->get();
    }

    public function findByField(string $field): Collection
    {
        return $this->model->newQuery()->where('field', $field)->get();
    }

    public function findByDocumentId(string $documentId): Collection
    {
        return $this->model->newQuery()->where('document_id', $documentId)->get();
    }

    public function findByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($fingerPrint) {
                $q->where('fingerprint', $fingerPrint->getValue());
            })
            ->get();
    }

    public function findByNamespace(string $namespace): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($namespace) {
                $q->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->get();
    }

    public function findByCluster(ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($cluster) {
                $q->where('cluster', $cluster->value);
            })
            ->get();
    }

    public function findByClusterKeyValue(string $key, string $value): Collection
    {
        $search = $key.':'.$value;

        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($search) {
                $q->where('cluster', 'LIKE', '%'.$search.'%');
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
            ->whereHas('document', function ($q) use ($namespace) {
                $q->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->get();
    }

    public function findByTokenAndCluster(string $token, ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->whereHas('document', function ($q) use ($cluster) {
                $q->where('cluster', $cluster->value);
            })
            ->get();
    }

    public function findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->whereHas('document', function ($q) use ($namespace) {
                $q->where('fingerprint', 'LIKE', $namespace.'|%');
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
        $query = $this->model->newQuery()->where('token', 'LIKE', $letter.'%');

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
        return $this->model->newQuery()
            ->where('token', $token)
            ->whereHas('document', function ($q) use ($cluster) {
                $q->where('cluster', $cluster->value);
            })
            ->select('document_id')
            ->distinct()
            ->pluck('document_id');
    }

    public function getDocumentIdsForTokenFieldAndCluster(string $token, string $field, ClusterVO $cluster): Collection
    {
        return $this->model->newQuery()
            ->where('token', $token)
            ->where('field', $field)
            ->whereHas('document', function ($q) use ($cluster) {
                $q->where('cluster', $cluster->value);
            })
            ->select('document_id')
            ->distinct()
            ->pluck('document_id');
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
        return $this->model->newQuery()->distinct('token')->count('token');
    }

    public function countByType(GramType $type): int
    {
        return $this->model->newQuery()->where('token_type', $type)->count();
    }

    public function countByField(string $field): int
    {
        return $this->model->newQuery()->where('field', $field)->count();
    }

    public function countByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($namespace) {
                $q->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->count();
    }

    public function deleteByDocumentId(string $documentId): int
    {
        return $this->model->newQuery()->where('document_id', $documentId)->delete();
    }

    public function deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($fingerPrint) {
                $q->where('fingerprint', $fingerPrint->getValue());
            })
            ->delete();
    }

    public function deleteByNamespace(string $namespace): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($namespace) {
                $q->where('fingerprint', 'LIKE', $namespace.'|%');
            })
            ->delete();
    }

    public function deleteByCluster(ClusterVO $cluster): int
    {
        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($cluster) {
                $q->where('cluster', $cluster->value);
            })
            ->delete();
    }

    public function deleteByClusterKeyValue(string $key, string $value): int
    {
        $search = $key.':'.$value;

        return $this->model->newQuery()
            ->whereHas('document', function ($q) use ($search) {
                $q->where('cluster', 'LIKE', '%'.$search.'%');
            })
            ->delete();
    }

    public function deleteByToken(string $token): int
    {
        return $this->model->newQuery()->where('token', $token)->delete();
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
