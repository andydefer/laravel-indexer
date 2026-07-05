<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Records\IndexableSearchResultRecord;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

/**
 * Main interface for the indexing service.
 *
 * Defines the contract for indexing, searching, deleting, and refreshing
 * indexable documents.
 */
interface IndexerInterface
{
    /**
     * Indexes a single entity.
     *
     * @param  IndexedDocumentRecord  $entity  The record to index
     */
    public function index(IndexedDocumentRecord $entity): void;

    /**
     * Indexes multiple entities.
     *
     * @param  IndexableRecordCollection  $records  Collection of records to index
     */
    public function indexMany(IndexableRecordCollection $records): void;

    /**
     * Deletes a document from the index.
     *
     * @param  IndexableFingerPrintVO  $fingerprint  The fingerprint of the document to delete
     */
    public function delete(IndexableFingerPrintVO $fingerprint): void;

    /**
     * Deletes multiple documents from the index.
     *
     * @param  IndexableFingerPrintVOCollection  $finger_prints  Collection of fingerprints to delete
     */
    public function deleteMany(IndexableFingerPrintVOCollection $finger_prints): void;

    /**
     * Clears the entire index.
     *
     * Removes all documents and tokens from the index.
     */
    public function clear(): void;

    /**
     * Checks if a document exists in the index.
     *
     * @param  IndexableFingerPrintVO  $fingerprint  The fingerprint to check
     * @return bool True if the document exists, false otherwise
     */
    public function exists(IndexableFingerPrintVO $fingerprint): bool;

    /**
     * Searches the index using the provided query.
     *
     * @param  SearchQueryRecord  $query  The search query
     * @return IndexableSearchResultCollection<IndexableSearchResultRecord> Collection of search results
     */
    public function search(SearchQueryRecord $query): IndexableSearchResultCollection;

    /**
     * Refreshes a document in the index (delete + re-index).
     *
     * @param  IndexedDocumentRecord  $entity  The record to refresh
     */
    public function refresh(IndexedDocumentRecord $entity): void;

    /**
     * Refreshes multiple documents in the index (delete + re-index).
     *
     * @param  IndexableRecordCollection  $records  Collection of records to refresh
     */
    public function refreshMany(IndexableRecordCollection $records): void;
}
