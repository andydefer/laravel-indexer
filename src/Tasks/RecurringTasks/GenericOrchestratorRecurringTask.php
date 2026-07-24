<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tasks\RecurringTasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
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

final class GenericOrchestratorRecurringTask extends AbstractRecurringTask
{
    protected function process(): void
    {
        $this->info(new DescriptionVO('Starting generic orchestrator: finding models to index...'));

        $app = $this->context->getLaravelApp();

        $indexerConfig = $app->make(IndexerConfigInterface::class);
        $uniqueTaskService = $app->make(UniqueTaskServiceInterface::class);

        $batchSize = $indexerConfig->getBatchSize();
        $modelIndexables = $indexerConfig->getModelIndexables();

        $totalDispatched = 0;
        $totalChunks = 0;

        foreach ($modelIndexables as $modelClass => $clusterType) {
            $this->info(new DescriptionVO("Processing {$modelClass}..."));

            $chunks = $this->getModelChunks($modelClass, $batchSize);

            foreach ($chunks as $chunk) {
                $indexableVO = new IndexableVO($modelClass, new ClusterVO($clusterType));

                $payload = StrictDataObject::from([
                    'indexable' => $indexableVO,
                    'ids' => $chunk,
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

    private function getModelChunks(string $modelClass, int $batchSize): array
    {
        /** @var Model $modelClass */
        $chunks = [];

        $modelClass::chunk($batchSize, function ($models) use (&$chunks) {
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

    protected function after(bool $success, ?DescriptionVO $error = null): void
    {
        if ($success) {
            $this->info(new DescriptionVO('Generic orchestrator task completed successfully'));
        } else {
            $this->error(new DescriptionVO("Generic orchestrator task failed: {$error->getValue()}"));
        }
    }
}
