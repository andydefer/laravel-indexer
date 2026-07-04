<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services;

use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Records\IndexableRecord;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\Services\Composants\IndexDeleter;
use AndyDefer\LaravelIndexer\Services\Composants\IndexSearcher;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexerService implements IndexerInterface
{
    public function __construct(
        private readonly IndexWriter $writer,
        private readonly IndexDeleter $deleter,
        private readonly IndexSearcher $searcher,
    ) {
        //
    }

    public function index(IndexableRecord $entity): void
    {
        $this->writer->index($entity);
    }

    public function indexMany(IndexableRecordCollection $records): void
    {
        $this->writer->indexMany($records);
    }

    public function delete(IndexableFingerPrintVO $finger_print): void
    {
        $this->deleter->delete($finger_print);
    }

    public function deleteMany(IndexableFingerPrintVOCollection $finger_prints): void
    {
        $this->deleter->deleteMany($finger_prints);
    }

    public function clear(): void
    {
        $this->deleter->clear();
    }

    public function exists(IndexableFingerPrintVO $finger_print): bool
    {
        return $this->searcher->exists($finger_print);
    }

    public function search(SearchQueryRecord $query): IndexableSearchResultCollection
    {
        return $this->searcher->search($query);
    }

    /**
     * Rafraîchit un document (delete + index).
     */
    public function refresh(IndexableRecord $entity): void
    {
        // 1. Supprimer l'ancien document
        $this->deleter->delete($entity->finger_print);

        // 2. Indexer le nouveau
        $this->writer->index($entity);
    }

    /**
     * Rafraîchit plusieurs documents (delete + index).
     */
    public function refreshMany(IndexableRecordCollection $records): void
    {
        // 1. Récupérer tous les fingerprints
        $fingerPrints = new IndexableFingerPrintVOCollection;
        foreach ($records as $record) {
            $fingerPrints->add($record->finger_print);
        }

        // 2. Supprimer tous les anciens documents
        $this->deleter->deleteMany($fingerPrints);

        // 3. Indexer tous les nouveaux
        $this->writer->indexMany($records);
    }
}
