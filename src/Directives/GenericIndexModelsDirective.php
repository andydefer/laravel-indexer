<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use Throwable;

final class GenericIndexModelsDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'index:models 
                {batch=50}#"Batch size for chunking (default: 50)" 
                {limit=?}#"Maximum number of items to index (unlimited if omitted)" 
                {models*}#"List of models to index (dot notation: App.Models.User)" 
                {--reindex}#"Delete then reindex all models" 
                {--count}#"Count indexed documents" 
                {--delete}#"Delete all indexed documents"';
    }

    public function getDescription(): string
    {
        return 'Index models from config (App.Models.User, App.Models.Hospital, etc.)';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('idx:models');
        $aliases->add('indexer:models');

        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true;
    }

    public function execute(): ExitCode
    {
        try {
            $app = $this->getApplication();

            $genericIndexer = $app->make(GenericIndexerInterface::class);
            $indexerConfig = $app->make(IndexerConfigInterface::class);

            $reindex = $this->getFlag('reindex');
            $count = $this->getFlag('count');
            $delete = $this->getFlag('delete');

            $batchSize = (int) $this->getArgument('batch');
            $limit = $this->getArgument('limit') !== null ? (int) $this->getArgument('limit') : null;

            $models = $this->getVariadic('models');

            if (empty($models)) {
                $this->error('❌ No models specified.');

                return ExitCode::INVALID_ARGUMENT;
            }

            $modelClasses = $this->resolveModelClasses($models, $indexerConfig);

            if (empty($modelClasses)) {
                return ExitCode::INVALID_ARGUMENT;
            }

            if ($count) {
                return $this->handleCount($genericIndexer, $indexerConfig, $modelClasses);
            }

            if ($delete) {
                return $this->handleDelete($genericIndexer, $indexerConfig, $modelClasses);
            }

            if ($reindex) {
                return $this->handleReindex($genericIndexer, $indexerConfig, $modelClasses, $batchSize, $limit);
            }

            return $this->handleIndex($genericIndexer, $indexerConfig, $modelClasses, $batchSize, $limit);

        } catch (Throwable $e) {
            $this->error('❌ '.$e->getMessage());

            return ExitCode::FAILURE;
        }
    }

    private function resolveModelClasses(array $models, IndexerConfigInterface $indexerConfig): array
    {
        $modelIndexables = $indexerConfig->getModelIndexables();
        $validClasses = array_keys($modelIndexables);
        $resolved = [];
        $hasError = false;

        foreach ($models as $model) {
            $modelClass = str_replace('.', '\\', $model);

            if (! class_exists($modelClass)) {
                $this->error("❌ Class '{$modelClass}' does not exist");
                $hasError = true;

                continue;
            }

            if (! in_array($modelClass, $validClasses, true)) {
                $this->error("❌ Model '{$modelClass}' is not configured in indexer.model_indexables");
                $hasError = true;

                continue;
            }

            $resolved[] = $modelClass;
        }

        if ($hasError && empty($resolved)) {
            $this->error('❌ No valid models found in config.');
        }

        return $resolved;
    }

    private function getModelLabel(string $modelClass): string
    {
        return $modelClass;
    }

    private function handleCount(GenericIndexerInterface $genericIndexer, IndexerConfigInterface $indexerConfig, array $modelClasses): ExitCode
    {
        $modelIndexables = $indexerConfig->getModelIndexables();
        $total = 0;

        foreach ($modelClasses as $modelClass) {
            $cluster = $modelIndexables[$modelClass];
            $indexableVO = new IndexableVO($modelClass, new ClusterVO($cluster));

            $count = $genericIndexer->countIndexed($indexableVO);
            $total += $count;
            $label = $this->getModelLabel($modelClass);
            $this->info("📊 Indexed {$label}: {$count}");
        }

        $this->newLine();
        $this->info("📈 Total indexed: {$total}");

        return ExitCode::SUCCESS;
    }

    private function handleDelete(GenericIndexerInterface $genericIndexer, IndexerConfigInterface $indexerConfig, array $modelClasses): ExitCode
    {
        $modelIndexables = $indexerConfig->getModelIndexables();
        $total = 0;

        foreach ($modelClasses as $modelClass) {
            $cluster = $modelIndexables[$modelClass];
            $indexableVO = new IndexableVO($modelClass, new ClusterVO($cluster));

            $genericIndexer->deleteAll($indexableVO);
            $label = $this->getModelLabel($modelClass);
            $this->info("🗑️ All {$label} deleted from index");
            $total++;
        }

        $this->newLine();
        $this->info("🗑️ Total models cleared: {$total}");

        return ExitCode::SUCCESS;
    }

    private function handleReindex(GenericIndexerInterface $genericIndexer, IndexerConfigInterface $indexerConfig, array $modelClasses, int $batchSize, ?int $limit): ExitCode
    {
        $modelIndexables = $indexerConfig->getModelIndexables();
        $totalIndexed = 0;
        $totalSkipped = 0;

        foreach ($modelClasses as $modelClass) {
            $cluster = $modelIndexables[$modelClass];
            $indexableVO = new IndexableVO($modelClass, new ClusterVO($cluster));

            $genericIndexer->setBatchSize($batchSize);
            $genericIndexer->setLimit($limit);

            // Compter avant réindexation
            $beforeCount = $genericIndexer->countIndexed($indexableVO);

            $genericIndexer->reindexAll($indexableVO);

            // Compter après réindexation
            $afterCount = $genericIndexer->countIndexed($indexableVO);

            $indexed = $afterCount;
            $skipped = $beforeCount > 0 ? $beforeCount : 0;

            $totalIndexed += $indexed;
            $totalSkipped += $skipped;

            $label = $this->getModelLabel($modelClass);
            $this->info("🔄 All {$label} reindexed successfully");
            $this->line("   📊 {$indexed} items indexed, {$skipped} skipped");
            $this->line("   📦 Batch size: {$batchSize}, Limit: ".($limit ?? 'unlimited'));
        }

        $this->newLine();
        $this->info("📈 Reindexing complete: {$totalIndexed} total items indexed, {$totalSkipped} skipped");

        return ExitCode::SUCCESS;
    }

    private function handleIndex(GenericIndexerInterface $genericIndexer, IndexerConfigInterface $indexerConfig, array $modelClasses, int $batchSize, ?int $limit): ExitCode
    {
        $modelIndexables = $indexerConfig->getModelIndexables();
        $totalIndexed = 0;
        $totalSkipped = 0;

        foreach ($modelClasses as $modelClass) {
            $cluster = $modelIndexables[$modelClass];
            $indexableVO = new IndexableVO($modelClass, new ClusterVO($cluster));

            // Compter avant indexation
            $beforeCount = $genericIndexer->countIndexed($indexableVO);

            $genericIndexer->setBatchSize($batchSize);
            $genericIndexer->setLimit($limit);
            $genericIndexer->indexAll($indexableVO);

            // Compter après indexation
            $afterCount = $genericIndexer->countIndexed($indexableVO);

            $indexed = $afterCount - $beforeCount;
            $skipped = $beforeCount;

            $totalIndexed += $indexed;
            $totalSkipped += $skipped;

            $label = $this->getModelLabel($modelClass);

            if ($indexed > 0) {
                $this->info("✅ All {$label} indexed successfully");
                $this->line("   📊 {$indexed} new items indexed, {$skipped} already indexed");
            } elseif ($skipped > 0 && $indexed === 0) {
                $this->line("   ℹ️ All {$skipped} {$label} were already indexed");
            } else {
                $this->line("   ⚠️ No {$label} found to index");
            }

            $this->line("   📦 Batch size: {$batchSize}, Limit: ".($limit ?? 'unlimited'));
        }

        $this->newLine();

        if ($totalIndexed > 0) {
            $this->info("📈 Indexing complete: {$totalIndexed} new items indexed");
        } elseif ($totalSkipped > 0 && $totalIndexed === 0) {
            $this->info("ℹ️ All items were already indexed ({$totalSkipped} total)");
        } else {
            $this->info('⚠️ No items found to index');
        }

        return ExitCode::SUCCESS;
    }
}
