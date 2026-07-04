<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableEntityIdVO;

/**
 * Collection spécialisée pour les IndexableEntityIdVO.
 *
 * @method IndexableEntityIdVO|null first()
 * @method IndexableEntityIdVO|null last()
 * @method IndexableEntityIdVO|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method TypedCollection mapToType(callable $callback, string $targetClass)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 * @method self reverse()
 * @method self sort(int $flags = SORT_REGULAR)
 */
final class IndexableEntityIdVOCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(IndexableEntityIdVO::class);
    }

    /**
     * Récupère les IDs par namespace
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (IndexableEntityIdVO $id) => $id->belongsTo($namespace)
        );
    }

    /**
     * Récupère les IDs par multiple namespaces
     */
    public function filterByNamespaces(array $namespaces): self
    {
        return $this->filter(
            fn (IndexableEntityIdVO $id) => $id->belongsToAny($namespaces)
        );
    }

    /**
     * Récupère tous les IDs sous forme de StringTypedCollection
     */
    public function getIds(): StringTypedCollection
    {
        $ids = new StringTypedCollection;
        foreach ($this->items as $id) {
            $ids->add($id->getId());
        }

        return $ids;
    }

    /**
     * Récupère tous les namespaces sous forme de StringTypedCollection
     */
    public function getNamespaces(): StringTypedCollection
    {
        $namespaces = new StringTypedCollection;
        foreach ($this->items as $id) {
            $namespaces->add($id->getNamespace());
        }

        return $namespaces;
    }

    /**
     * Récupère les namespaces originaux (avec \)
     */
    public function getOriginalNamespaces(): StringTypedCollection
    {
        $namespaces = new StringTypedCollection;
        foreach ($this->items as $id) {
            $namespaces->add($id->getOriginalNamespace());
        }

        return $namespaces;
    }

    /**
     * Groupe les IDs par namespace
     *
     * @return array<string, self>
     */
    public function groupByNamespace(): array
    {
        $groups = [];
        foreach ($this->items as $id) {
            $namespace = $id->getNamespace();
            if (! isset($groups[$namespace])) {
                $groups[$namespace] = new self;
            }
            $groups[$namespace]->add($id);
        }

        return $groups;
    }

    /**
     * Vérifie si un ID spécifique existe
     */
    public function containsId(string $id): bool
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id) {
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
        foreach ($this->items as $item) {
            if ($item->belongsTo($namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère un ID par sa valeur brute
     */
    public function findByValue(string $value): ?IndexableEntityIdVO
    {
        foreach ($this->items as $item) {
            if ($item->getValue() === $value) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Récupère un ID par son ID et namespace
     */
    public function findByIdAndNamespace(string $id, string $namespace): ?IndexableEntityIdVO
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id && $item->belongsTo($namespace)) {
                return $item;
            }
        }

        return null;
    }
}
