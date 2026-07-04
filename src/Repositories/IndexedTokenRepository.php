<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class IndexedTokenRepository extends AbstractRepository
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
            $query->whereHas('document', function ($q) use ($filters) {
                $q->whereJsonContains('cluster->'.$filters->cluster_key, $filters->cluster_value);
            });
        }

        if ($filters->document_ids !== null && ! $filters->document_ids->isEmpty()) {
            $query->whereIn('document_id', $filters->document_ids->toArray());
        }
    }
}
