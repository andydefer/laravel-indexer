<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Records\IndexableRecord;
use AndyDefer\LaravelIndexer\Records\IndexableSearchResultRecord;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

interface IndexerInterface
{
    /**
     * Indexe une entité.
     */
    public function index(IndexableRecord $entity): void;

    /**
     * Indexe plusieurs entités.
     */
    public function indexMany(IndexableRecordCollection $records): void;

    /**
     * Supprime un document de l'index.
     */
    public function delete(IndexableFingerPrintVO $finger_print): void;

    /**
     * Supprime plusieurs documents.
     */
    public function deleteMany(IndexableFingerPrintVOCollection $finger_prints): void;

    /**
     * Vide l'index.
     */
    public function clear(): void;

    /**
     * Vérifie si un document existe.
     */
    public function exists(IndexableFingerPrintVO $finger_print): bool;

    /**
     * Recherche dans l'index.
     *
     * @return IndexableSearchResultCollection<IndexableSearchResultRecord>
     */
    public function search(SearchQueryRecord $query): IndexableSearchResultCollection;

    /**
     * Rafraîchit un document dans l'index (delete + index).
     */
    public function refresh(IndexableRecord $entity): void;

    /**
     * Rafraîchit plusieurs documents dans l'index (delete + index).
     */
    public function refreshMany(IndexableRecordCollection $records): void;
}
