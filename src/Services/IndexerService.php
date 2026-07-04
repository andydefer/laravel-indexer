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
        // TODO: Implémenter la logique de rafraîchissement
        // 1. Récupérer le fingerprint de l'entité
        // 2. Supprimer l'ancien document
        // 3. Indexer le nouveau
    }

    /**
     * Rafraîchit plusieurs documents (delete + index).
     */
    public function refreshMany(IndexableRecordCollection $records): void
    {
        // TODO: Implémenter la logique de rafraîchissement multiple
        // 1. Récupérer tous les fingerprints
        // 2. Supprimer tous les anciens documents
        // 3. Indexer tous les nouveaux
    }
}
