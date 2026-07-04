<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

/**
 * Collection spécialisée pour les IndexableFingerPrintVO.
 *
 * @method IndexableFingerPrintVO|null first()
 * @method IndexableFingerPrintVO|null last()
 * @method IndexableFingerPrintVO|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method TypedCollection mapToType(callable $callback, string $targetClass)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 * @method self reverse()
 * @method self sort(int $flags = SORT_REGULAR)
 */
final class IndexableFingerPrintVOCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(IndexableFingerPrintVO::class);
    }

    /**
     * Récupère les fingerprints par namespace
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (IndexableFingerPrintVO $fp) => $fp->belongsTo($namespace)
        );
    }

    /**
     * Récupère les fingerprints par multiple namespaces
     */
    public function filterByNamespaces(array $namespaces): self
    {
        return $this->filter(
            fn (IndexableFingerPrintVO $fp) => $fp->belongsToAny($namespaces)
        );
    }

    /**
     * Récupère tous les IDs sous forme de StringTypedCollection
     */
    public function getIds(): StringTypedCollection
    {
        $ids = new StringTypedCollection;
        foreach ($this->items as $fp) {
            $ids->add($fp->getId());
        }

        return $ids;
    }

    /**
     * Récupère tous les namespaces sous forme de StringTypedCollection
     */
    public function getNamespaces(): StringTypedCollection
    {
        $namespaces = new StringTypedCollection;
        foreach ($this->items as $fp) {
            $namespaces->add($fp->getNamespace());
        }

        return $namespaces;
    }

    /**
     * Récupère les namespaces originaux (avec \)
     */
    public function getOriginalNamespaces(): StringTypedCollection
    {
        $namespaces = new StringTypedCollection;
        foreach ($this->items as $fp) {
            $namespaces->add($fp->getOriginalNamespace());
        }

        return $namespaces;
    }

    /**
     * Groupe les fingerprints par namespace
     *
     * @return array<string, self>
     */
    public function groupByNamespace(): array
    {
        $groups = [];
        foreach ($this->items as $fp) {
            $namespace = $fp->getNamespace();
            if (! isset($groups[$namespace])) {
                $groups[$namespace] = new self;
            }
            $groups[$namespace]->add($fp);
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
     * Récupère un fingerprint par sa valeur brute
     */
    public function findByValue(string $value): ?IndexableFingerPrintVO
    {
        foreach ($this->items as $item) {
            if ($item->getValue() === $value) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Récupère un fingerprint par son ID et namespace
     */
    public function findByIdAndNamespace(string $id, string $namespace): ?IndexableFingerPrintVO
    {
        foreach ($this->items as $item) {
            if ($item->getId() === $id && $item->belongsTo($namespace)) {
                return $item;
            }
        }

        return null;
    }
}
