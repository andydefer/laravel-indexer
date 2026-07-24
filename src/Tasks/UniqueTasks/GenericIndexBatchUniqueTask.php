<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tasks\UniqueTasks;

use AndyDefer\ConsoleWriter\Console\Contracts\ConsoleInterface;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use Illuminate\Database\Eloquent\Model;

final class GenericIndexBatchUniqueTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $payload = $this->context->getPayload();

        if (! $payload->has('indexable') || ! $payload->has('ids') || empty($payload->ids)) {
            $this->error(new DescriptionVO('Invalid payload: missing indexable or ids'));

            return;
        }

        /** @var IndexableVO $indexableVO */
        $indexableVO = IndexableVO::from($payload->indexable);
        $ids = $payload->ids;

        $this->info(new DescriptionVO('Processing batch of '.count($ids).' items for '.$indexableVO->getModelClass().'...'));

        $app = $this->context->getLaravelApp();

        $indexer = $app->make(IndexerInterface::class);
        $documentRepository = $app->make(IndexedDocumentRepositoryInterface::class);
        $console = $app->make(ConsoleInterface::class);

        $modelClass = $indexableVO->getModelClass();
        $records = new IndexableRecordCollection;
        $skipped = 0;
        $indexed = 0;

        foreach ($ids as $id) {
            /** @var Model&Indexable $model */
            $model = $modelClass::find($id);

            if (! $model) {
                $console->alertWarning("Item {$id} not found, skipping");
                $skipped++;

                continue;
            }

            if (! $model->shouldBeIndexed()) {
                $this->info(new DescriptionVO("Item {$id} should not be indexed, skipping"));
                $skipped++;

                continue;
            }

            $fingerPrint = IndexableFingerPrintVO::fromParts($model->getMorphClass(), (string) $model->getKey());

            if ($documentRepository->existsByFingerPrint($fingerPrint)) {
                $this->info(new DescriptionVO("Item {$id} already indexed, deleting and re-indexing"));
                $documentRepository->deleteByFingerPrint($fingerPrint);
            }

            $cluster = new ClusterVO($indexableVO->getClusterType());
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
