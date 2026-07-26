<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Collection spécialisée pour les IndexableVO.
 *
 * @method IndexableVO|null first()
 * @method IndexableVO|null last()
 * @method IndexableVO|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method TypedCollection mapToType(callable $callback, string $targetClass)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 * @method self reverse()
 * @method self sort(int $flags = SORT_REGULAR)
 */
final class IndexableVOCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(IndexableVO::class);
    }

    /**
     * Récupère les IDs de tous les éléments.
     *
     * @return array<int|string>
     */
    public function getIds(): array
    {
        $ids = [];
        foreach ($this->items as $item) {
            $ids[] = $item->getId();
        }

        return $ids;
    }

    /**
     * Récupère les classes de modèle de tous les éléments.
     *
     * @return array<string>
     */
    public function getModelClasses(): array
    {
        $classes = [];
        foreach ($this->items as $item) {
            $classes[] = $item->getModelClass();
        }

        return $classes;
    }

    /**
     * Récupère les instances des modèles pour tous les éléments en une seule requête par classe.
     * Les modèles non trouvés sont ignorés silencieusement.
     * Les instances sont retournées dans l'ordre de la collection.
     *
     * @return Collection<int, Model&Indexable>
     */
    public function getModelInstances(): Collection
    {
        // Grouper les IDs par classe de modèle
        $groupedIds = [];
        foreach ($this->items as $item) {
            $class = $item->getModelClass();
            $id = $item->getId();
            if (! isset($groupedIds[$class])) {
                $groupedIds[$class] = [];
            }
            $groupedIds[$class][] = $id;
        }

        // Une seule requête par classe de modèle
        $models = [];
        foreach ($groupedIds as $class => $ids) {
            /** @var Model&Indexable $class */
            $found = $class::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($found as $model) {
                $models[$model->getKey()] = $model;
            }
        }

        // ✅ Retourner les modèles dans l'ordre de la collection
        $result = [];
        foreach ($this->items as $item) {
            $id = $item->getId();
            if (isset($models[$id])) {
                $result[] = $models[$id];
            }
        }

        return new Collection($result);
    }

    /**
     * Groupe les éléments par classe de modèle.
     *
     * @return array<string, self>
     */
    public function groupByModelClass(): array
    {
        $groups = [];
        foreach ($this->items as $item) {
            $class = $item->getModelClass();
            if (! isset($groups[$class])) {
                $groups[$class] = new self;
            }
            $groups[$class]->add($item);
        }

        return $groups;
    }

    /**
     * Filtre les éléments par classe de modèle.
     */
    public function filterByModelClass(string $modelClass): self
    {
        return $this->filter(
            fn (IndexableVO $item) => $item->getModelClass() === $modelClass
        );
    }

    /**
     * Filtre les éléments par liste de classes de modèles.
     *
     * @param  array<string>  $modelClasses  Liste des FQCN
     */
    public function filterByModelClasses(array $modelClasses): self
    {
        return $this->filter(
            fn (IndexableVO $item) => in_array($item->getModelClass(), $modelClasses, true)
        );
    }

    /**
     * Vérifie si un ID spécifique existe.
     */
    public function containsId(int|string $id): bool
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si une classe de modèle spécifique existe.
     */
    public function containsModelClass(string $modelClass): bool
    {
        foreach ($this->items as $item) {
            if ($item->getModelClass() === $modelClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère un élément par son ID.
     */
    public function findById(int|string $id): ?IndexableVO
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Récupère les éléments par classe de modèle et IDs.
     *
     * @param  string  $modelClass  FQCN du modèle
     * @param  array<int|string>  $ids  IDs à rechercher
     */
    public function filterByModelClassAndIds(string $modelClass, array $ids): self
    {
        return $this->filter(
            fn (IndexableVO $item) => $item->getModelClass() === $modelClass && in_array($item->getId(), $ids, true)
        );
    }
}
