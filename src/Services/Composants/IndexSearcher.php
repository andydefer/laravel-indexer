<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexSearcher
{
    public function __construct()
    {
        //
    }

    public function exists(IndexableFingerPrintVO $finger_print): bool
    {
        // TODO: Implémenter la logique d'existence
        return false;
    }

    public function search(SearchQueryRecord $query): IndexableSearchResultCollection
    {
        // TODO: Implémenter la logique de recherche
        return new IndexableSearchResultCollection;
    }
}
