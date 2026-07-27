<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class ClusterFilterApplier
{
    public function applyCluster(Builder $query, ClusterVO $cluster, string $column = 'cluster'): void
    {
        if (! $cluster->hasMode()) {
            throw new InvalidArgumentException('Cluster must have a mode (AND, OR or NOT) to apply to query');
        }

        $clusterPairs = $cluster->all();

        if (empty($clusterPairs)) {
            return;
        }

        if ($cluster->isOr()) {
            $query->where(function ($subQuery) use ($clusterPairs, $column) {
                foreach ($clusterPairs as $key => $value) {
                    $subQuery->orWhere($column, 'LIKE', '%'.$key.':'.$value.'%');
                }
            });
        } elseif ($cluster->isNot()) {
            $query->where(function ($subQuery) use ($clusterPairs, $column) {
                foreach ($clusterPairs as $key => $value) {
                    $subQuery->where($column, 'NOT LIKE', '%'.$key.':'.$value.'%');
                }
            });
        } else {
            foreach ($clusterPairs as $key => $value) {
                $query->where($column, 'LIKE', '%'.$key.':'.$value.'%');
            }
        }
    }

    /**
     * Applique les clusters directement sur une colonne (pas de relation)
     */
    public function applyClusters(
        Builder $query,
        ClusterVOCollection $clusters,
        ?string $operator = 'AND',
        string $column = 'cluster'
    ): void {
        if ($clusters->isEmpty()) {
            return;
        }

        $operator = $operator ?? 'AND';
        $this->validateOperator($operator);

        if ($operator === 'AND') {
            foreach ($clusters as $cluster) {
                $this->applyCluster($query, $cluster, $column);
            }
        } elseif ($operator === 'OR') {
            $query->where(function ($subQuery) use ($clusters, $column) {
                foreach ($clusters as $cluster) {
                    $subQuery->orWhere(function ($innerQuery) use ($cluster, $column) {
                        $this->applyCluster($innerQuery, $cluster, $column);
                    });
                }
            });
        } elseif ($operator === 'NOT') {
            // Pour NOT, on construit la condition positive puis on l'inverse
            $positiveQuery = clone $query;
            foreach ($clusters as $cluster) {
                $this->applyCluster($positiveQuery, $cluster, $column);
            }

            // Récupérer les IDs à exclure
            $excludeIds = $positiveQuery->pluck('id')->toArray();

            if (! empty($excludeIds)) {
                $query->whereNotIn('id', $excludeIds);
            }
        }
    }

    /**
     * Applique les clusters via une relation Eloquent
     */
    public function applyClustersOnRelation(
        Builder $query,
        ClusterVOCollection $clusters,
        ?string $operator = 'AND',
        string $relation = 'document',
        string $column = 'cluster'
    ): void {
        if ($clusters->isEmpty()) {
            return;
        }

        $operator = $operator ?? 'AND';
        $this->validateOperator($operator);

        if ($operator === 'AND') {
            foreach ($clusters as $cluster) {
                $query->whereHas($relation, function ($q) use ($cluster, $column) {
                    $this->applyCluster($q, $cluster, $column);
                });
            }
        } elseif ($operator === 'OR') {
            $query->where(function ($subQuery) use ($clusters, $relation, $column) {
                foreach ($clusters as $cluster) {
                    $subQuery->orWhereHas($relation, function ($innerQuery) use ($cluster, $column) {
                        $this->applyCluster($innerQuery, $cluster, $column);
                    });
                }
            });
        } elseif ($operator === 'NOT') {
            // Pour NOT, on construit la condition positive puis on l'inverse
            $positiveQuery = clone $query;
            foreach ($clusters as $cluster) {
                $positiveQuery->whereHas($relation, function ($q) use ($cluster, $column) {
                    $this->applyCluster($q, $cluster, $column);
                });
            }

            // Récupérer les IDs à exclure
            $excludeIds = $positiveQuery->pluck('id')->toArray();

            if (! empty($excludeIds)) {
                $query->whereNotIn('id', $excludeIds);
            }
        }
    }

    private function validateOperator(string $operator): void
    {
        $validOperators = ['AND', 'OR', 'NOT'];
        if (! in_array($operator, $validOperators, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid operator. Expected "AND", "OR" or "NOT", got "%s"', $operator)
            );
        }
    }
}
