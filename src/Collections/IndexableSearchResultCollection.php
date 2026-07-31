<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Records\IndexableSearchResultRecord;

/**
 * A typed collection for search result records.
 *
 * Provides specialized filtering, grouping, and data extraction methods
 * for working with collections of search results.
 *
 * @method IndexableSearchResultRecord|null first()
 * @method IndexableSearchResultRecord|null last()
 * @method IndexableSearchResultRecord|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method TypedCollection mapToType(callable $callback, string $targetClass)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 * @method self reverse()
 * @method self sort(int $flags = SORT_REGULAR)
 */
final class IndexableSearchResultCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(IndexableSearchResultRecord::class);
    }

    /**
     * Returns a new collection containing results matching the given field.
     *
     * @param  string  $field  The field name to filter by
     * @return self A new collection with matching items
     */
    public function filterByField(string $field): self
    {
        return $this->filter(
            fn (IndexableSearchResultRecord $result): bool => $result->field === $field
        );
    }

    /**
     * Returns a new collection containing results with the given gram type.
     *
     * @param  GramType  $type  The gram type to filter by
     * @return self A new collection with matching items
     */
    public function filterByGramType(GramType $type): self
    {
        return $this->filter(
            fn (IndexableSearchResultRecord $result): bool => $result->gram_type === $type
        );
    }

    /**
     * Returns a new collection containing results with the given gram value.
     *
     * @param  string  $value  The gram value to filter by
     * @return self A new collection with matching items
     */
    public function filterByGramValue(string $value): self
    {
        return $this->filter(
            fn (IndexableSearchResultRecord $result): bool => $result->gram_value === $value
        );
    }

    /**
     * Returns a new collection containing results from the given namespace.
     *
     * @param  string  $namespace  The namespace to filter by
     * @return self A new collection with matching items
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (IndexableSearchResultRecord $result): bool => $result->item->fingerprint->belongsTo($namespace)
        );
    }

    /**
     * Extracts all entity IDs from the search results.
     *
     * @return StringTypedCollection A collection of entity IDs as strings
     */
    public function getIds(): StringTypedCollection
    {
        $ids = new StringTypedCollection;

        foreach ($this->items as $result) {
            $ids->add($result->item->fingerprint->getId());
        }

        return $ids;
    }

    /**
     * Extracts all fingerprints from the search results.
     *
     * @return IndexableFingerPrintVOCollection A collection of all fingerprints
     */
    public function getFingerprints(): IndexableFingerPrintVOCollection
    {
        $fingerprints = new IndexableFingerPrintVOCollection;

        foreach ($this->items as $result) {
            $fingerprints->add($result->item->fingerprint);
        }

        return $fingerprints;
    }

    /**
     * Extracts all indexed document records from the search results.
     *
     * @return IndexableRecordCollection A collection of indexed document records
     */
    public function getItems(): IndexableRecordCollection
    {
        $items = new IndexableRecordCollection;

        foreach ($this->items as $result) {
            $items->add($result->item);
        }

        return $items;
    }

    /**
     * Groups search results by their field name.
     *
     * @return array<string, self> An associative array mapping field name to a collection of results
     */
    public function groupByField(): array
    {
        $groups = [];

        foreach ($this->items as $result) {
            $field = $result->field;

            if (! isset($groups[$field])) {
                $groups[$field] = new self;
            }

            $groups[$field]->add($result);
        }

        return $groups;
    }

    /**
     * Groups search results by their gram type.
     *
     * @return array<string, self> An associative array mapping gram type to a collection of results
     */
    public function groupByGramType(): array
    {
        $groups = [];

        foreach ($this->items as $result) {
            $type = $result->gram_type->value;

            if (! isset($groups[$type])) {
                $groups[$type] = new self;
            }

            $groups[$type]->add($result);
        }

        return $groups;
    }

    /**
     * Groups search results by their namespace.
     *
     * @return array<string, self> An associative array mapping namespace to a collection of results
     */
    public function groupByNamespace(): array
    {
        $groups = [];

        foreach ($this->items as $result) {
            $namespace = $result->item->fingerprint->getNamespace();

            if (! isset($groups[$namespace])) {
                $groups[$namespace] = new self;
            }

            $groups[$namespace]->add($result);
        }

        return $groups;
    }
}
