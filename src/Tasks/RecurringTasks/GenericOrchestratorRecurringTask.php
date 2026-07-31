<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tasks\RecurringTasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\Task\Abstract\AbstractRecurringTask;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Recurring task that orchestrates the indexing of multiple model classes.
 *
 * This task retrieves all configured model classes and dispatches batch
 * tasks for each chunk of models. It acts as a coordinator that breaks
 * down large indexing operations into manageable unique tasks.
 *
 * The task uses the configured batch size to determine chunk sizes and
 * registers each chunk as a separate UniqueTask for processing.
 */
final class GenericOrchestratorRecurringTask extends AbstractRecurringTask
{
    /**
     * {@inheritDoc}
     */
    protected function process(): void
    {
        $this->info(new DescriptionVO('Starting generic orchestrator: finding models to index...'));

        $app = $this->context->getLaravelApp();

        $indexerConfig = $app->make(IndexerConfigInterface::class);
        $uniqueTaskService = $app->make(UniqueTaskServiceInterface::class);

        $batchSize = $indexerConfig->getBatchSize();
        $modelClasses = $indexerConfig->getModelIndexables();

        $totalDispatched = 0;
        $totalChunks = 0;

        foreach ($modelClasses as $modelClass) {
            $this->info(new DescriptionVO("Processing {$modelClass}..."));

            $chunks = $this->getModelChunks($modelClass, $batchSize);

            foreach ($chunks as $chunk) {
                $collection = $this->buildIndexableCollection($modelClass, $chunk);

                $payload = StrictDataObject::from([
                    'items' => $collection,
                ]);

                $config = UniqueTaskConfigRecord::from([
                    'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
                    'max_attempts' => new MaxFailedAttemptsVO(3),
                    'grace_period' => new DurationVO(3600),
                    'description' => new DescriptionVO("Batch task for indexing {$modelClass}"),
                ]);

                $uniqueTaskService->register(
                    new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
                    $payload,
                    $config
                );

                $totalChunks++;
                $totalDispatched += count($chunk);
            }

            $this->info(new DescriptionVO("Dispatched {$totalDispatched} {$modelClass} in {$totalChunks} batches"));
        }

        $this->info(new DescriptionVO("Orchestrator completed: {$totalDispatched} items dispatched in {$totalChunks} batch tasks"));
    }

    /**
     * {@inheritDoc}
     */
    protected function after(bool $success, ?DescriptionVO $error = null): void
    {
        if ($success) {
            $this->info(new DescriptionVO('Generic orchestrator task completed successfully'));
        } else {
            $this->error(new DescriptionVO("Generic orchestrator task failed: {$error->getValue()}"));
        }
    }

    /**
     * Retrieves chunks of model IDs for the given model class.
     *
     * Models that should not be indexed (according to shouldBeIndexed())
     * are excluded from the chunks.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @param  int  $batchSize  The maximum size of each chunk
     * @return array<int, array<int, int|string>> An array of ID chunks
     */
    private function getModelChunks(string $modelClass, int $batchSize): array
    {
        /** @var class-string<Model> $modelClass */
        $chunks = [];

        $modelClass::chunk($batchSize, function (Collection $models) use (&$chunks) {
            $ids = [];

            foreach ($models as $model) {
                if ($model->shouldBeIndexed()) {
                    $ids[] = $model->getKey();
                }
            }

            if (! empty($ids)) {
                $chunks[] = $ids;
            }
        });

        return $chunks;
    }

    /**
     * Builds an IndexableVOCollection from a chunk of model IDs.
     *
     * @param  string  $modelClass  The model class name
     * @param  array<int, int|string>  $chunk  The chunk of model IDs
     * @return IndexableVOCollection The collection of indexable value objects
     */
    private function buildIndexableCollection(string $modelClass, array $chunk): IndexableVOCollection
    {
        $collection = new IndexableVOCollection;

        foreach ($chunk as $id) {
            $collection->add(new IndexableVO($modelClass, $id));
        }

        return $collection;
    }
}
