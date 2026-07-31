<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelCluster\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;

/**
 * A typed collection for IndexedDocumentRecord objects.
 *
 * Provides specialized filtering, grouping, and data extraction methods
 * for working with collections of indexed document records.
 *
 * @method IndexedDocumentRecord|null first()
 * @method IndexedDocumentRecord|null last()
 * @method IndexedDocumentRecord|null find(callable $callback)
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
        parent::__construct(IndexedDocumentRecord::class);
    }

    /**
     * Splits the collection into chunks of the specified size.
     *
     * @param  int  $size  The maximum size of each chunk
     * @return array<int, self> An array of collection chunks
     */
    public function chunk(int $size): array
    {
        if ($size <= 0) {
            return [];
        }

        $chunks = [];
        $currentChunk = new self;

        foreach ($this->items as $item) {
            $currentChunk->add($item);

            if ($currentChunk->count() >= $size) {
                $chunks[] = $currentChunk;
                $currentChunk = new self;
            }
        }

        if ($currentChunk->isNotEmpty()) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Returns a new collection containing only records belonging to the given namespace.
     *
     * @param  string  $namespace  The namespace to filter by
     * @return self A new collection with matching items
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (IndexedDocumentRecord $record): bool => $record->fingerprint->belongsTo($namespace)
        );
    }

    /**
     * Returns a new collection containing records belonging to any of the given namespaces.
     *
     * @param  string[]  $namespaces  List of namespaces to filter by
     * @return self A new collection with matching items
     */
    public function filterByNamespaces(array $namespaces): self
    {
        return $this->filter(
            fn (IndexedDocumentRecord $record): bool => $record->fingerprint->belongsToAny($namespaces)
        );
    }

    /**
     * Returns a new collection containing records matching the given cluster key/value pair.
     *
     * @param  string  $key  The cluster key to match
     * @param  string  $value  The cluster value to match
     * @return self A new collection with matching items
     */
    public function filterByCluster(string $key, string $value): self
    {
        return $this->filter(
            fn (IndexedDocumentRecord $record): bool => $record->cluster->get($key) === $value
        );
    }

    /**
     * Returns a new collection containing records matching all given cluster key/value pairs.
     *
     * @param  array<string, string>  $clusters  Associative array of key => value pairs
     * @return self A new collection with matching items
     */
    public function filterByClusters(array $clusters): self
    {
        return $this->filter(
            function (IndexedDocumentRecord $record) use ($clusters): bool {
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
     * Returns a new collection containing records with a data field matching the given value.
     *
     * @param  string  $field  The data field name
     * @param  mixed  $value  The expected value (strict comparison)
     * @return self A new collection with matching items
     */
    public function filterByDataField(string $field, mixed $value): self
    {
        return $this->filter(
            fn (IndexedDocumentRecord $record): bool => ($record->data[$field] ?? null) === $value
        );
    }

    /**
     * Extracts all fingerprints from the records in the collection.
     *
     * @return IndexableFingerPrintVOCollection A collection of all fingerprints
     */
    public function getFingerprints(): IndexableFingerPrintVOCollection
    {
        $fingerprints = new IndexableFingerPrintVOCollection;

        foreach ($this->items as $record) {
            $fingerprints->add($record->fingerprint);
        }

        return $fingerprints;
    }

    /**
     * Extracts all clusters from the records in the collection.
     *
     * @return ClusterVOCollection A collection of all clusters
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
     * Extracts all entity IDs from the records in the collection.
     *
     * @return StringTypedCollection A collection of entity IDs as strings
     */
    public function getIds(): StringTypedCollection
    {
        $ids = new StringTypedCollection;

        foreach ($this->items as $record) {
            $ids->add($record->fingerprint->getId());
        }

        return $ids;
    }

    /**
     * Returns a collection of all unique data field names present in the records.
     *
     * @return StringTypedCollection A collection of unique field names
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
     * Groups records by their namespace.
     *
     * @return array<string, self> An associative array mapping namespace to a collection of records
     */
    public function groupByNamespace(): array
    {
        $groups = [];

        foreach ($this->items as $record) {
            $namespace = $record->fingerprint->getNamespace();

            if (! isset($groups[$namespace])) {
                $groups[$namespace] = new self;
            }

            $groups[$namespace]->add($record);
        }

        return $groups;
    }

    /**
     * Groups records by a specific cluster key's value.
     *
     * Records with a null or missing value are grouped under the key 'null'.
     *
     * @param  string  $key  The cluster key to group by
     * @return array<string, self> An associative array mapping value to a collection of records
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
     * Filters records using a callback that operates on their data payload.
     *
     * @param  callable(StrictAssociative): bool  $callback  The filter callback
     * @return self A new collection with matching items
     */
    public function searchData(callable $callback): self
    {
        return $this->filter(
            fn (IndexedDocumentRecord $record): bool => $callback($record->data)
        );
    }

    /**
     * Filters records containing the given text in any of their data fields.
     *
     * The search is case-insensitive and only works on string values.
     *
     * @param  string  $search  The text to search for
     * @return self A new collection with matching items
     */
    public function searchTextInData(string $search): self
    {
        $searchLower = strtolower($search);

        return $this->filter(
            function (IndexedDocumentRecord $record) use ($searchLower): bool {
                foreach ($record->data->toArray() as $value) {
                    if (is_string($value) && str_contains(strtolower($value), $searchLower)) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Returns a new collection containing records that have the specified data field.
     *
     * @param  string  $field  The data field name to check for
     * @return self A new collection with matching items
     */
    public function hasDataField(string $field): self
    {
        return $this->filter(
            fn (IndexedDocumentRecord $record): bool => isset($record->data[$field])
        );
    }

    /**
     * Sorts the collection by a data field value.
     *
     * @param  string  $field  The data field to sort by
     * @param  bool  $ascending  True for ascending order, false for descending
     * @return self A new sorted collection
     */
    public function sortByDataField(string $field, bool $ascending = true): self
    {
        $sorted = $this->items;

        usort($sorted, function (IndexedDocumentRecord $a, IndexedDocumentRecord $b) use ($field, $ascending): int {
            $valueA = $a->data[$field] ?? null;
            $valueB = $b->data[$field] ?? null;

            if ($valueA === $valueB) {
                return 0;
            }

            $comparison = $valueA < $valueB ? -1 : 1;

            return $ascending ? $comparison : -$comparison;
        });

        $newCollection = new self;

        foreach ($sorted as $item) {
            $newCollection->add($item);
        }

        return $newCollection;
    }

    /**
     * Extracts values from a specific data field across all records.
     *
     * Only scalar values are included in the result.
     *
     * @param  string  $field  The data field to pluck
     * @return StringTypedCollection A collection of scalar values as strings
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
     * Checks if any record in the collection has the given entity ID.
     *
     * @param  string  $id  The entity ID to check for
     * @return bool True if at least one record matches
     */
    public function containsId(string $id): bool
    {
        foreach ($this->items as $record) {
            if ($record->fingerprint->getId() === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if any record in the collection belongs to the given namespace.
     *
     * @param  string  $namespace  The namespace to check for
     * @return bool True if at least one record matches
     */
    public function containsNamespace(string $namespace): bool
    {
        foreach ($this->items as $record) {
            if ($record->fingerprint->belongsTo($namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Finds a record by its entity ID.
     *
     * @param  string  $id  The entity ID to search for
     * @return IndexedDocumentRecord|null The matching record, or null if not found
     */
    public function findById(string $id): ?IndexedDocumentRecord
    {
        foreach ($this->items as $record) {
            if ($record->fingerprint->getId() === $id) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Finds a record by its entity ID and namespace combination.
     *
     * @param  string  $id  The entity ID to search for
     * @param  string  $namespace  The namespace to match
     * @return IndexedDocumentRecord|null The matching record, or null if not found
     */
    public function findByIdAndNamespace(string $id, string $namespace): ?IndexedDocumentRecord
    {
        foreach ($this->items as $record) {
            if ($record->fingerprint->getId() === $id && $record->fingerprint->belongsTo($namespace)) {
                return $record;
            }
        }

        return null;
    }
}
