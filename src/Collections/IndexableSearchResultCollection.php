<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Records\IndexableSearchResultRecord;

/**
 * Collection spécialisée pour les résultats de recherche.
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
     * Filtre les résultats par champ.
     */
    public function filterByField(string $field): self
    {
        return $this->filter(
            fn (IndexableSearchResultRecord $result) => $result->field === $field
        );
    }

    /**
     * Filtre les résultats par type de gram.
     */
    public function filterByGramType(GramType $type): self
    {
        return $this->filter(
            fn (IndexableSearchResultRecord $result) => $result->gram_type === $type
        );
    }

    /**
     * Filtre les résultats par valeur de gram.
     */
    public function filterByGramValue(string $value): self
    {
        return $this->filter(
            fn (IndexableSearchResultRecord $result) => $result->gram_value === $value
        );
    }

    /**
     * Filtre les résultats par namespace.
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (IndexableSearchResultRecord $result) => $result->item->fingerprint->belongsTo($namespace)
        );
    }

    /**
     * Récupère les IDs des résultats.
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
     * Récupère les fingerprints des résultats.
     */
    public function getFingerPrints(): IndexableFingerPrintVOCollection
    {
        $fingerPrints = new IndexableFingerPrintVOCollection;
        foreach ($this->items as $result) {
            $fingerPrints->add($result->item->fingerprint);
        }

        return $fingerPrints;
    }

    /**
     * Récupère les items (IndexedDocumentRecord) des résultats.
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
     * Groupe les résultats par champ.
     *
     * @return array<string, self>
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
     * Groupe les résultats par type de gram.
     *
     * @return array<string, self>
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
     * Groupe les résultats par namespace.
     *
     * @return array<string, self>
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
