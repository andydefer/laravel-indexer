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
 * A typed collection for IndexableVO objects.
 *
 * Provides specialized filtering, grouping, and model retrieval methods
 * for working with collections of indexable value objects.
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
     * Extracts all entity IDs from the value objects in the collection.
     *
     * @return array<int|string> An array of entity IDs
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
     * Extracts all model class names from the value objects in the collection.
     *
     * @return array<string> An array of fully-qualified class names
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
     * Retrieves the actual model instances for all value objects in a single query per class.
     *
     * This method groups IDs by model class and executes one query per class,
     * which is more efficient than retrieving each model individually.
     *
     * Missing models are silently ignored. Results are returned in the order
     * of the original collection.
     *
     * @return Collection<int, Model&Indexable> A collection of model instances
     */
    public function getModelInstances(): Collection
    {
        $groupedIds = [];

        // Group IDs by model class
        foreach ($this->items as $item) {
            $class = $item->getModelClass();
            $id = $item->getId();

            if (! isset($groupedIds[$class])) {
                $groupedIds[$class] = [];
            }

            $groupedIds[$class][] = $id;
        }

        // Execute one query per model class
        $models = [];

        foreach ($groupedIds as $class => $ids) {
            /** @var class-string<Model&Indexable> $class */
            $found = $class::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($found as $model) {
                $models[$model->getKey()] = $model;
            }
        }

        // Return models in the order of the original collection
        $orderedModels = [];

        foreach ($this->items as $item) {
            $id = $item->getId();

            if (isset($models[$id])) {
                $orderedModels[] = $models[$id];
            }
        }

        return new Collection($orderedModels);
    }

    /**
     * Groups value objects by their model class.
     *
     * @return array<string, self> An associative array mapping model class to a collection of value objects
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
     * Returns a new collection containing value objects from the given model class.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @return self A new collection with matching items
     */
    public function filterByModelClass(string $modelClass): self
    {
        return $this->filter(
            fn (IndexableVO $item): bool => $item->getModelClass() === $modelClass
        );
    }

    /**
     * Returns a new collection containing value objects from any of the given model classes.
     *
     * @param  string[]  $modelClasses  List of fully-qualified class names
     * @return self A new collection with matching items
     */
    public function filterByModelClasses(array $modelClasses): self
    {
        return $this->filter(
            fn (IndexableVO $item): bool => in_array($item->getModelClass(), $modelClasses, true)
        );
    }

    /**
     * Checks if any value object in the collection has the given entity ID.
     *
     * @param  int|string  $id  The entity ID to check for
     * @return bool True if at least one value object matches
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
     * Checks if any value object in the collection belongs to the given model class.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @return bool True if at least one value object matches
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
     * Finds a value object by its entity ID.
     *
     * @param  int|string  $id  The entity ID to search for
     * @return IndexableVO|null The matching value object, or null if not found
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
     * Returns a new collection containing value objects matching the given model class and IDs.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @param  array<int|string>  $ids  The entity IDs to filter by
     * @return self A new collection with matching items
     */
    public function filterByModelClassAndIds(string $modelClass, array $ids): self
    {
        return $this->filter(
            fn (IndexableVO $item): bool => $item->getModelClass() === $modelClass && in_array($item->getId(), $ids, true)
        );
    }
}
