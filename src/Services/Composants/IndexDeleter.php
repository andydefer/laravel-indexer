<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexDeleter
{
    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
    ) {}

    public function delete(IndexableFingerPrintVO $finger_print): void
    {
        // Supprimer le document et ses tokens (cascade via la base de données)
        $this->documentRepository->deleteByFingerPrint($finger_print);
    }

    public function deleteMany(IndexableFingerPrintVOCollection $finger_prints): void
    {
        foreach ($finger_prints as $finger_print) {
            $this->documentRepository->deleteByFingerPrint($finger_print);
        }
    }

    public function clear(): void
    {
        // Supprimer tous les tokens puis tous les documents
        // Ou simplement tronquer les tables
        $this->tokenRepository->getModel()->newQuery()->delete();
        $this->documentRepository->getModel()->newQuery()->delete();
    }
}
