<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;

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
     *
     * @param  IndexableFingerprintVO  $fingerprint  The fingerprint of the document to delete
     */
    public function delete(IndexableFingerprintVO $fingerprint): void
    {
        $this->documentRepository->deleteByFingerPrint($fingerprint);
    }

    /**
     * Deletes multiple documents by their fingerprints.
     *
     * @param  IndexableFingerPrintVOCollection  $fingerprints  The collection of fingerprints to delete
     */
    public function deleteMany(IndexableFingerPrintVOCollection $fingerprints): void
    {
        foreach ($fingerprints as $fingerprint) {
            $this->documentRepository->deleteByFingerPrint($fingerprint);
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
