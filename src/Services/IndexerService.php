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

/**
 * Main service orchestrating all indexing operations.
 *
 * Acts as a facade that delegates to specialized components:
 * - IndexWriter for creating/reindexing documents
 * - IndexDeleter for removing documents
 * - IndexSearcher for searching and existence checks
 *
 * @implements IndexerInterface
 */
final class IndexerService implements IndexerInterface
{
    public function __construct(
        private readonly IndexWriter $writer,
        private readonly IndexDeleter $deleter,
        private readonly IndexSearcher $searcher,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function index(IndexableRecord $entity): void
    {
        $this->writer->index($entity);
    }

    /**
     * {@inheritDoc}
     */
    public function indexMany(IndexableRecordCollection $records): void
    {
        $this->writer->indexMany($records);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(IndexableFingerPrintVO $fingerPrint): void
    {
        $this->deleter->delete($fingerPrint);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMany(IndexableFingerPrintVOCollection $fingerPrints): void
    {
        $this->deleter->deleteMany($fingerPrints);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        $this->deleter->clear();
    }

    /**
     * {@inheritDoc}
     */
    public function exists(IndexableFingerPrintVO $fingerPrint): bool
    {
        return $this->searcher->exists($fingerPrint);
    }

    /**
     * {@inheritDoc}
     */
    public function search(SearchQueryRecord $query): IndexableSearchResultCollection
    {
        return $this->searcher->search($query);
    }

    /**
     * {@inheritDoc}
     */
    public function refresh(IndexableRecord $entity): void
    {
        $this->deleter->delete($entity->fingerprint);
        $this->writer->index($entity);
    }

    /**
     * {@inheritDoc}
     */
    public function refreshMany(IndexableRecordCollection $records): void
    {
        $fingerPrints = new IndexableFingerPrintVOCollection;

        foreach ($records as $record) {
            $fingerPrints->add($record->fingerprint);
        }

        $this->deleter->deleteMany($fingerPrints);
        $this->writer->indexMany($records);
    }
}
