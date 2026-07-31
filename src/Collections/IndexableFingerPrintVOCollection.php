<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;

/**
 * A specialized collection for IndexableFingerprintVO objects.
 *
 * Provides convenient filtering, grouping, and querying methods for
 * collections of entity fingerprints.
 *
 * @method IndexableFingerprintVO|null first()
 * @method IndexableFingerprintVO|null last()
 * @method IndexableFingerprintVO|null find(callable $callback)
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
        parent::__construct(IndexableFingerprintVO::class);
    }

    /**
     * Returns a new collection containing only fingerprints belonging to the given namespace.
     *
     * @param  string  $namespace  The namespace to filter by (e.g., 'App\Models\User')
     * @return self A new collection with matching items
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (IndexableFingerprintVO $fingerprint): bool => $fingerprint->belongsTo($namespace)
        );
    }

    /**
     * Returns a new collection containing fingerprints belonging to any of the given namespaces.
     *
     * @param  string[]  $namespaces  List of namespaces to filter by
     * @return self A new collection with matching items
     */
    public function filterByNamespaces(array $namespaces): self
    {
        return $this->filter(
            fn (IndexableFingerprintVO $fingerprint): bool => $fingerprint->belongsToAny($namespaces)
        );
    }

    /**
     * Extracts all entity IDs from the fingerprints in the collection.
     *
     * @return StringTypedCollection A collection of entity IDs as strings
     */
    public function getIds(): StringTypedCollection
    {
        $ids = new StringTypedCollection;

        foreach ($this->items as $fingerprint) {
            $ids->add($fingerprint->getId());
        }

        return $ids;
    }

    /**
     * Extracts all namespaces from the fingerprints in the collection.
     *
     * Namespaces are returned in their stored format (with backslashes).
     *
     * @return StringTypedCollection A collection of namespaces as strings
     */
    public function getNamespaces(): StringTypedCollection
    {
        $namespaces = new StringTypedCollection;

        foreach ($this->items as $fingerprint) {
            $namespaces->add($fingerprint->getNamespace());
        }

        return $namespaces;
    }

    /**
     * Groups fingerprints by their namespace.
     *
     * @return array<string, self> An associative array mapping namespace to a collection of fingerprints
     */
    public function groupByNamespace(): array
    {
        $groups = [];

        foreach ($this->items as $fingerprint) {
            $namespace = $fingerprint->getNamespace();

            if (! isset($groups[$namespace])) {
                $groups[$namespace] = new self;
            }

            $groups[$namespace]->add($fingerprint);
        }

        return $groups;
    }

    /**
     * Checks if any fingerprint in the collection has the given entity ID.
     *
     * @param  string  $id  The entity ID to check for
     * @return bool True if at least one fingerprint matches
     */
    public function containsId(string $id): bool
    {
        foreach ($this->items as $fingerprint) {
            if ($fingerprint->getId() === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if any fingerprint in the collection belongs to the given namespace.
     *
     * @param  string  $namespace  The namespace to check for
     * @return bool True if at least one fingerprint matches
     */
    public function containsNamespace(string $namespace): bool
    {
        foreach ($this->items as $fingerprint) {
            if ($fingerprint->belongsTo($namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Finds a fingerprint by its raw string value.
     *
     * @param  string  $value  The raw fingerprint string (e.g., 'App\Models\User|123')
     * @return IndexableFingerprintVO|null The matching fingerprint, or null if not found
     */
    public function findByValue(string $value): ?IndexableFingerprintVO
    {
        foreach ($this->items as $fingerprint) {
            if ($fingerprint->getValue() === $value) {
                return $fingerprint;
            }
        }

        return null;
    }

    /**
     * Finds a fingerprint by its entity ID and namespace combination.
     *
     * @param  string  $id  The entity ID to match
     * @param  string  $namespace  The namespace to match
     * @return IndexableFingerprintVO|null The matching fingerprint, or null if not found
     */
    public function findByIdAndNamespace(string $id, string $namespace): ?IndexableFingerprintVO
    {
        foreach ($this->items as $fingerprint) {
            if ($fingerprint->getId() === $id && $fingerprint->belongsTo($namespace)) {
                return $fingerprint;
            }
        }

        return null;
    }
}
