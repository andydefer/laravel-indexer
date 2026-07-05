<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

/**
 * Service for deleting indexed documents and their associated tokens.
 *
 * Provides methods for single, bulk, and full index clearing operations.
 */
final class IndexDeleter
{
    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
    ) {}

    /**
     * Deletes a single document by its fingerprint.
     *
     * The associated tokens are automatically deleted via database cascade.
     */
    public function delete(IndexableFingerPrintVO $fingerPrint): void
    {
        $this->documentRepository->deleteByFingerPrint($fingerPrint);
    }

    /**
     * Deletes multiple documents by their fingerprints.
     */
    public function deleteMany(IndexableFingerPrintVOCollection $fingerPrints): void
    {
        foreach ($fingerPrints as $fingerPrint) {
            $this->documentRepository->deleteByFingerPrint($fingerPrint);
        }
    }

    /**
     * Clears the entire index by removing all documents and tokens.
     */
    public function clear(): void
    {
        $this->tokenRepository->getModel()->newQuery()->delete();
        $this->documentRepository->getModel()->newQuery()->delete();
    }
}
