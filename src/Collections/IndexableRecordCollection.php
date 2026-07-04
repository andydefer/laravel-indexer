<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\Records\IndexableRecord;

/**
 * Collection spécialisée pour les IndexableRecord.
 *
 * @method IndexableRecord|null first()
 * @method IndexableRecord|null last()
 * @method IndexableRecord|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method TypedCollection mapToType(callable $callback, string $targetClass)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 * @method self reverse()
 * @method self sort(int $flags = SORT_REGULAR)
 */
final class IndexableRecordCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(IndexableRecord::class);
    }

    /**
     * Filtre par namespace
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (IndexableRecord $record) => $record->id->belongsTo($namespace)
        );
    }

    /**
     * Filtre par multiple namespaces
     */
    public function filterByNamespaces(array $namespaces): self
    {
        return $this->filter(
            fn (IndexableRecord $record) => $record->id->belongsToAny($namespaces)
        );
    }

    /**
     * Filtre par cluster
     */
    public function filterByCluster(string $key, string $value): self
    {
        return $this->filter(
            fn (IndexableRecord $record) => $record->cluster->get($key) === $value
        );
    }

    /**
     * Filtre par multiple clusters
     */
    public function filterByClusters(array $clusters): self
    {
        return $this->filter(
            function (IndexableRecord $record) use ($clusters) {
                foreach ($clusters as $key => $value) {
                    if ($record->cluster->get($key) !== $value) {
                        return false;
                    }
                }

                return true;
            }
        );
    }

    /**
     * Filtre par champ de données
     */
    public function filterByDataField(string $field, mixed $value): self
    {
        return $this->filter(
            fn (IndexableRecord $record) => ($record->data[$field] ?? null) === $value
        );
    }

    /**
     * Récupère tous les IDs
     */
    public function getIds(): IndexableEntityIdVOCollection
    {
        $ids = new IndexableEntityIdVOCollection;
        foreach ($this->items as $record) {
            $ids->add($record->id);
        }

        return $ids;
    }

    /**
     * Récupère tous les clusters
     */
    public function getClusters(): ClusterVOCollection
    {
        $clusters = new ClusterVOCollection;
        foreach ($this->items as $record) {
            $clusters->add($record->cluster);
        }

        return $clusters;
    }

    /**
     * Récupère les IDs sous forme de StringTypedCollection
     */
    public function getIdValues(): StringTypedCollection
    {
        $ids = new StringTypedCollection;
        foreach ($this->items as $record) {
            $ids->add($record->id->getValue());
        }

        return $ids;
    }

    /**
     * Récupère les noms de champs uniques présents dans les données
     */
    public function getUniqueDataFields(): StringTypedCollection
    {
        $fields = new StringTypedCollection;
        foreach ($this->items as $record) {
            foreach (array_keys($record->data->toArray()) as $field) {
                if (! $fields->contains($field)) {
                    $fields->add($field);
                }
            }
        }

        return $fields;
    }

    /**
     * Groupe par namespace
     *
     * @return array<string, self>
     */
    public function groupByNamespace(): array
    {
        $groups = [];
        foreach ($this->items as $record) {
            $namespace = $record->id->getNamespace();
            if (! isset($groups[$namespace])) {
                $groups[$namespace] = new self;
            }
            $groups[$namespace]->add($record);
        }

        return $groups;
    }

    /**
     * Groupe par cluster
     *
     * @return array<string, self>
     */
    public function groupByClusterKey(string $key): array
    {
        $groups = [];
        foreach ($this->items as $record) {
            $value = $record->cluster->get($key) ?? 'null';
            if (! isset($groups[$value])) {
                $groups[$value] = new self;
            }
            $groups[$value]->add($record);
        }

        return $groups;
    }

    /**
     * Recherche dans les données avec un callback
     */
    public function searchData(callable $callback): self
    {
        return $this->filter(
            fn (IndexableRecord $record) => $callback($record->data)
        );
    }

    /**
     * Recherche un texte dans tous les champs de données
     */
    public function searchTextInData(string $search): self
    {
        return $this->filter(
            function (IndexableRecord $record) use ($search) {
                $search = strtolower($search);
                foreach ($record->data->toArray() as $value) {
                    if (is_string($value) && str_contains(strtolower($value), $search)) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Récupère les enregistrements qui contiennent un champ spécifique
     */
    public function hasDataField(string $field): self
    {
        return $this->filter(
            fn (IndexableRecord $record) => isset($record->data[$field])
        );
    }

    /**
     * Trie par un champ de données
     */
    public function sortByDataField(string $field, bool $ascending = true): self
    {
        $sorted = $this->items;
        usort($sorted, function (IndexableRecord $a, IndexableRecord $b) use ($field, $ascending) {
            $valA = $a->data[$field] ?? null;
            $valB = $b->data[$field] ?? null;

            if ($valA === $valB) {
                return 0;
            }

            $comparison = $valA < $valB ? -1 : 1;

            return $ascending ? $comparison : -$comparison;
        });

        $newCollection = new self;
        foreach ($sorted as $item) {
            $newCollection->add($item);
        }

        return $newCollection;
    }

    /**
     * Récupère les valeurs d'un champ de données
     */
    public function pluckDataField(string $field): StringTypedCollection
    {
        $values = new StringTypedCollection;
        foreach ($this->items as $record) {
            if (isset($record->data[$field])) {
                $value = $record->data[$field];
                if (is_scalar($value)) {
                    $values->add((string) $value);
                }
            }
        }

        return $values;
    }

    /**
     * Vérifie si un ID spécifique existe
     */
    public function containsId(string $id): bool
    {
        foreach ($this->items as $record) {
            if ($record->id->getId() === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si un namespace spécifique existe
     */
    public function containsNamespace(string $namespace): bool
    {
        foreach ($this->items as $record) {
            if ($record->id->belongsTo($namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère un enregistrement par ID
     */
    public function findById(string $id): ?IndexableRecord
    {
        foreach ($this->items as $record) {
            if ($record->id->getId() === $id) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Récupère un enregistrement par ID et namespace
     */
    public function findByIdAndNamespace(string $id, string $namespace): ?IndexableRecord
    {
        foreach ($this->items as $record) {
            if ($record->id->getId() === $id && $record->id->belongsTo($namespace)) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Récupère les enregistrements qui contiennent toutes les clés de cluster
     */
    public function withClusterKeys(array $keys): self
    {
        return $this->filter(
            fn (IndexableRecord $record) => $record->cluster->hasAll($keys)
        );
    }

    /**
     * Récupère les enregistrements qui contiennent au moins une clé de cluster
     */
    public function withAnyClusterKeys(array $keys): self
    {
        return $this->filter(
            fn (IndexableRecord $record) => $record->cluster->hasAny($keys)
        );
    }
}
