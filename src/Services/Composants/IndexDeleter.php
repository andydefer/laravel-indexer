<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexDeleter
{
    public function __construct()
    {
        //
    }

    public function delete(IndexableFingerPrintVO $finger_print): void
    {
        // TODO: Implémenter la logique de suppression
    }

    public function deleteMany(IndexableFingerPrintVOCollection $finger_prints): void
    {
        // TODO: Implémenter la logique de suppression multiple
    }

    public function clear(): void
    {
        // TODO: Implémenter la logique de vidage
    }
}
