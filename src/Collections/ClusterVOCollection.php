<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

/**
 * Collection spécialisée pour les ClusterVO.
 *
 * @method ClusterVO|null first()
 * @method ClusterVO|null last()
 * @method ClusterVO|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method TypedCollection mapToType(callable $callback, string $targetClass)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 * @method self reverse()
 * @method self sort(int $flags = SORT_REGULAR)
 */
final class ClusterVOCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ClusterVO::class);
    }

    /**
     * Filtre les clusters par clé
     */
    public function filterByKey(string $key): self
    {
        return $this->filter(
            fn (ClusterVO $cluster) => $cluster->has($key)
        );
    }

    /**
     * Filtre les clusters par paire clé-valeur
     */
    public function filterByPair(string $key, string $value): self
    {
        return $this->filter(
            fn (ClusterVO $cluster) => $cluster->get($key) === $value
        );
    }

    /**
     * Filtre les clusters par plusieurs paires clé-valeur
     */
    public function filterByPairs(array $pairs): self
    {
        return $this->filter(
            function (ClusterVO $cluster) use ($pairs) {
                foreach ($pairs as $key => $value) {
                    if ($cluster->get($key) !== $value) {
                        return false;
                    }
                }

                return true;
            }
        );
    }

    /**
     * Récupère toutes les valeurs d'une clé spécifique
     */
    public function getValuesForKey(string $key): StringTypedCollection
    {
        $values = new StringTypedCollection;
        foreach ($this->items as $cluster) {
            if ($value = $cluster->get($key)) {
                $values->add($value);
            }
        }

        return $values;
    }

    /**
     * Récupère toutes les clés uniques
     */
    public function getUniqueKeys(): StringTypedCollection
    {
        $keys = new StringTypedCollection;
        foreach ($this->items as $cluster) {
            foreach (array_keys($cluster->all()) as $key) {
                if (! $keys->contains($key)) {
                    $keys->add($key);
                }
            }
        }

        return $keys;
    }

    /**
     * Groupe les clusters par une clé
     *
     * @return array<string, self>
     */
    public function groupByKey(string $key): array
    {
        $groups = [];
        foreach ($this->items as $cluster) {
            $value = $cluster->get($key) ?? 'null';
            if (! isset($groups[$value])) {
                $groups[$value] = new self;
            }
            $groups[$value]->add($cluster);
        }

        return $groups;
    }

    /**
     * Fusionne tous les clusters en un seul
     */
    public function mergeAll(): ClusterVO
    {
        $merged = [];
        foreach ($this->items as $cluster) {
            $merged = array_merge($merged, $cluster->all());
        }

        return new ClusterVO($this->buildClusterString($merged));
    }

    /**
     * Vérifie si une clé existe dans au moins un cluster
     */
    public function hasKey(string $key): bool
    {
        foreach ($this->items as $cluster) {
            if ($cluster->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si une paire clé-valeur existe dans au moins un cluster
     */
    public function hasPair(string $key, string $value): bool
    {
        foreach ($this->items as $cluster) {
            if ($cluster->get($key) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère les clusters qui contiennent toutes les clés
     */
    public function filterWithAllKeys(array $keys): self
    {
        return $this->filter(
            fn (ClusterVO $cluster) => $cluster->hasAll($keys)
        );
    }

    /**
     * Récupère les clusters qui contiennent au moins une des clés
     */
    public function filterWithAnyKeys(array $keys): self
    {
        return $this->filter(
            fn (ClusterVO $cluster) => $cluster->hasAny($keys)
        );
    }

    /**
     * Construit une chaîne de cluster à partir d'un tableau
     */
    private function buildClusterString(array $pairs): string
    {
        $parts = [];
        foreach ($pairs as $key => $value) {
            $parts[] = $key.'-'.$value;
        }

        return implode('|', $parts);
    }
}
