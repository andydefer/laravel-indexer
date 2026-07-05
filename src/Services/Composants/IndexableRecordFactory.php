<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

/**
 * Factory for creating IndexedDocumentRecord instances from Indexable entities.
 *
 * Transforms an indexable entity into a document record that can be persisted
 * to the indexed documents storage.
 */
final class IndexableRecordFactory
{
    /**
     * Converts an Indexable entity into an IndexedDocumentRecord.
     *
     * @param  Indexable  $entity  The entity to convert
     * @param  ClusterVO  $cluster  The cluster configuration for the document
     * @return IndexedDocumentRecord The converted document record
     */
    public static function convert(Indexable $entity, ClusterVO $cluster): IndexedDocumentRecord
    {
        $data = $entity->getIndexableData();
        $key = $entity->getKey();
        $morphClass = $entity->getMorphClass();

        $fingerprint = new IndexableFingerPrintVO($morphClass.'|'.$key);

        return new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            cluster: $cluster,
            data: $data,
        );
    }
}
