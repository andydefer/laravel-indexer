<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tasks\UniqueTasks;

use AndyDefer\ConsoleWriter\Console\Contracts\ConsoleInterface;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\Repositories\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;

final class GenericIndexBatchUniqueTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $payload = $this->context->getPayload();

        if (! $payload->has('items') || empty($payload->items)) {
            $this->error(new DescriptionVO('Invalid payload: missing items or empty collection'));

            return;
        }

        /** @var IndexableVOCollection $items */
        $items = IndexableVOCollection::from($payload->items);

        if ($items->isEmpty()) {
            $this->error(new DescriptionVO('Invalid payload: items collection is empty'));

            return;
        }

        $this->info(new DescriptionVO('Processing batch of '.$items->count().' items...'));

        $app = $this->context->getLaravelApp();

        $indexer = $app->make(IndexerInterface::class);
        $documentRepository = $app->make(IndexedDocumentRepositoryInterface::class);
        $console = $app->make(ConsoleInterface::class);

        $records = new IndexableRecordCollection;
        $skipped = 0;
        $indexed = 0;

        // ✅ Récupérer toutes les instances en une seule fois
        $instances = $items->getModelInstances();

        // ✅ Créer un index des instances par ID pour un accès rapide
        $instancesById = [];
        foreach ($instances as $instance) {
            $instancesById[$instance->getKey()] = $instance;
        }

        foreach ($items as $item) {
            $id = $item->getId();
            $model = $instancesById[$id] ?? null;

            if ($model === null) {
                $console->alertWarning("Item {$id} not found, skipping");
                $skipped++;

                continue;
            }

            if (! $model->shouldBeIndexed()) {
                $this->info(new DescriptionVO("Item {$id} should not be indexed, skipping"));
                $skipped++;

                continue;
            }

            $fingerprint = IndexableFingerPrintVO::fromParts($model->getMorphClass(), (string) $model->getKey());

            if ($documentRepository->existsByFingerPrint($fingerprint)) {
                $this->info(new DescriptionVO("Item {$id} already indexed, deleting and re-indexing"));
                $documentRepository->deleteByFingerPrint($fingerprint);
            }

            $cluster = $model->getIndexableCluster();
            $records->add(IndexableRecordFactory::convert($model, $cluster));
            $indexed++;
        }

        if ($records->isNotEmpty()) {
            $indexer->indexMany($records);
            $this->info(new DescriptionVO("Indexed {$indexed} items, skipped {$skipped} items"));
        } else {
            $this->info(new DescriptionVO("No items to index in this batch (skipped {$skipped})"));
        }
    }

    protected function after(bool $success, ?DescriptionVO $error = null): void
    {
        if ($success) {
            $this->info(new DescriptionVO('Batch indexation completed successfully'));
        } else {
            $this->error(new DescriptionVO("Batch indexation failed: {$error->getValue()}"));
        }
    }
}
