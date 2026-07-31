<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use Throwable;

/**
 * CLI directive for indexing Eloquent models.
 *
 * This directive provides a command-line interface to index, reindex, count,
 * and delete indexed documents for configured model classes.
 *
 * Usage examples:
 *   # Index models
 *   bin/directive index:models [App.Models.User,App.Models.Hospital]
 *
 *   # Reindex all models (delete then reindex)
 *   bin/directive index:models [App.Models.User,App.Models.Hospital] --reindex
 *
 *   # Count indexed documents
 *   bin/directive index:models [App.Models.User] --count
 *
 *   # Delete all indexed documents
 *   bin/directive index:models [App.Models.User] --delete
 *
 *   # Customize batch size and limit
 *   bin/directive index:models 10 50 [App.Models.User]
 *
 * @see AbstractDirective
 */
final class GenericIndexModelsDirective extends AbstractDirective
{
    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'Index models from config (App.Models.User, App.Models.Hospital, etc.) with dynamic clusters';
    }

    /**
     * {@inheritDoc}
     */
    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection;
        $aliases->add('idx:models');
        $aliases->add('indexer:models');

        return $aliases;
    }

    /**
     * {@inheritDoc}
     */
    public function shouldBootLaravel(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
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
                return $this->handleCount($genericIndexer, $modelClasses);
            }

            if ($delete) {
                return $this->handleDelete($genericIndexer, $modelClasses);
            }

            if ($reindex) {
                return $this->handleReindex($genericIndexer, $modelClasses, $batchSize, $limit);
            }

            return $this->handleIndex($genericIndexer, $modelClasses, $batchSize, $limit);
        } catch (Throwable $e) {
            $this->error('❌ '.$e->getMessage());

            return ExitCode::FAILURE;
        }
    }

    /**
     * Resolves dot-notation model names to fully-qualified class names.
     *
     * Validates that each class exists and is configured in the indexer config.
     *
     * @param  string[]  $models  The model names in dot notation
     * @param  IndexerConfigInterface  $indexerConfig  The configuration instance
     * @return string[] The resolved fully-qualified class names
     */
    private function resolveModelClasses(array $models, IndexerConfigInterface $indexerConfig): array
    {
        $validClasses = $indexerConfig->getModelIndexables();
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

    /**
     * Returns a human-readable label for a model class.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @return string The model label
     */
    private function getModelLabel(string $modelClass): string
    {
        return $modelClass;
    }

    /**
     * Handles the count operation for one or more model classes.
     *
     * @param  GenericIndexerInterface  $genericIndexer  The indexer service
     * @param  string[]  $modelClasses  The model classes to count
     * @return ExitCode The exit code
     */
    private function handleCount(GenericIndexerInterface $genericIndexer, array $modelClasses): ExitCode
    {
        $total = 0;

        foreach ($modelClasses as $modelClass) {
            $count = $genericIndexer->countIndexed($modelClass);
            $total += $count;
            $label = $this->getModelLabel($modelClass);
            $this->info("📊 Indexed {$label}: {$count}");
        }

        $this->newLine();
        $this->info("📈 Total indexed: {$total}");

        return ExitCode::SUCCESS;
    }

    /**
     * Handles the delete operation for one or more model classes.
     *
     * @param  GenericIndexerInterface  $genericIndexer  The indexer service
     * @param  string[]  $modelClasses  The model classes to delete
     * @return ExitCode The exit code
     */
    private function handleDelete(GenericIndexerInterface $genericIndexer, array $modelClasses): ExitCode
    {
        $total = 0;

        foreach ($modelClasses as $modelClass) {
            $genericIndexer->deleteAll($modelClass);
            $label = $this->getModelLabel($modelClass);
            $this->info("🗑️ All {$label} deleted from index");
            $total++;
        }

        $this->newLine();
        $this->info("🗑️ Total models cleared: {$total}");

        return ExitCode::SUCCESS;
    }

    /**
     * Handles the reindex operation for one or more model classes.
     *
     * Reindexing deletes all existing index entries and creates new ones.
     *
     * @param  GenericIndexerInterface  $genericIndexer  The indexer service
     * @param  string[]  $modelClasses  The model classes to reindex
     * @param  int  $batchSize  The batch size for chunking
     * @param  int|null  $limit  The maximum number of items to index
     * @return ExitCode The exit code
     */
    private function handleReindex(
        GenericIndexerInterface $genericIndexer,
        array $modelClasses,
        int $batchSize,
        ?int $limit
    ): ExitCode {
        $totalIndexed = 0;
        $totalSkipped = 0;

        foreach ($modelClasses as $modelClass) {
            $genericIndexer->setBatchSize($batchSize);
            $genericIndexer->setLimit($limit);

            $beforeCount = $genericIndexer->countIndexed($modelClass);

            $genericIndexer->reindexAll($modelClass);

            $afterCount = $genericIndexer->countIndexed($modelClass);

            $indexed = $afterCount;
            $skipped = $beforeCount > 0 ? $beforeCount : 0;

            $totalIndexed += $indexed;
            $totalSkipped += $skipped;

            $label = $this->getModelLabel($modelClass);
            $this->info("🔄 All {$label} reindexed successfully with dynamic clusters");
            $this->line("   📊 {$indexed} items indexed, {$skipped} skipped");
            $this->line("   📦 Batch size: {$batchSize}, Limit: ".($limit ?? 'unlimited'));
        }

        $this->newLine();
        $this->info("📈 Reindexing complete: {$totalIndexed} total items indexed, {$totalSkipped} skipped");

        return ExitCode::SUCCESS;
    }

    /**
     * Handles the index operation for one or more model classes.
     *
     * Only new models that are not already indexed will be processed.
     *
     * @param  GenericIndexerInterface  $genericIndexer  The indexer service
     * @param  string[]  $modelClasses  The model classes to index
     * @param  int  $batchSize  The batch size for chunking
     * @param  int|null  $limit  The maximum number of items to index
     * @return ExitCode The exit code
     */
    private function handleIndex(
        GenericIndexerInterface $genericIndexer,
        array $modelClasses,
        int $batchSize,
        ?int $limit
    ): ExitCode {
        $totalIndexed = 0;
        $totalSkipped = 0;

        foreach ($modelClasses as $modelClass) {
            $beforeCount = $genericIndexer->countIndexed($modelClass);

            $genericIndexer->setBatchSize($batchSize);
            $genericIndexer->setLimit($limit);
            $genericIndexer->indexAll($modelClass);

            $afterCount = $genericIndexer->countIndexed($modelClass);

            $indexed = $afterCount - $beforeCount;
            $skipped = $beforeCount;

            $totalIndexed += $indexed;
            $totalSkipped += $skipped;

            $label = $this->getModelLabel($modelClass);

            if ($indexed > 0) {
                $this->info("✅ All {$label} indexed successfully with dynamic clusters");
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
            $this->info("📈 Indexing complete: {$totalIndexed} new items indexed with dynamic clusters");
        } elseif ($totalSkipped > 0 && $totalIndexed === 0) {
            $this->info("ℹ️ All items were already indexed ({$totalSkipped} total)");
        } else {
            $this->info('⚠️ No items found to index');
        }

        return ExitCode::SUCCESS;
    }
}
