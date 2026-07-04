<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexableRecordFactory
{
    /**
     * Convertit une entité Indexable en IndexedDocumentRecord.
     *
     * @param  Indexable  $entity  L'entité à convertir
     * @param  ClusterVO  $cluster  Cluster obligatoire
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
