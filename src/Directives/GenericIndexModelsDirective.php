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
        return 'index:models {batch=50} {limit=?} {models*} {--reindex} {--count} {--delete}';
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

        foreach ($modelClasses as $modelClass) {
            $cluster = $modelIndexables[$modelClass];
            $indexableVO = new IndexableVO($modelClass, new ClusterVO($cluster));

            $count = $genericIndexer->countIndexed($indexableVO);
            $label = $this->getModelLabel($modelClass);
            $this->info("📊 Indexed {$label}: {$count}");
        }

        return ExitCode::SUCCESS;
    }

    private function handleDelete(GenericIndexerInterface $genericIndexer, IndexerConfigInterface $indexerConfig, array $modelClasses): ExitCode
    {
        $modelIndexables = $indexerConfig->getModelIndexables();

        foreach ($modelClasses as $modelClass) {
            $cluster = $modelIndexables[$modelClass];
            $indexableVO = new IndexableVO($modelClass, new ClusterVO($cluster));

            $genericIndexer->deleteAll($indexableVO);
            $label = $this->getModelLabel($modelClass);
            $this->info("🗑️ All {$label} deleted from index");
        }

        return ExitCode::SUCCESS;
    }

    private function handleReindex(GenericIndexerInterface $genericIndexer, IndexerConfigInterface $indexerConfig, array $modelClasses, int $batchSize, ?int $limit): ExitCode
    {
        $modelIndexables = $indexerConfig->getModelIndexables();

        foreach ($modelClasses as $modelClass) {
            $cluster = $modelIndexables[$modelClass];
            $indexableVO = new IndexableVO($modelClass, new ClusterVO($cluster));

            $genericIndexer->setBatchSize($batchSize);
            $genericIndexer->setLimit($limit);
            $genericIndexer->reindexAll($indexableVO);
            $label = $this->getModelLabel($modelClass);
            $this->info("🔄 All {$label} reindexed successfully (batch: {$batchSize}, limit: ".($limit ?? 'unlimited').')');
        }

        return ExitCode::SUCCESS;
    }

    private function handleIndex(GenericIndexerInterface $genericIndexer, IndexerConfigInterface $indexerConfig, array $modelClasses, int $batchSize, ?int $limit): ExitCode
    {
        $modelIndexables = $indexerConfig->getModelIndexables();

        foreach ($modelClasses as $modelClass) {
            $cluster = $modelIndexables[$modelClass];
            $indexableVO = new IndexableVO($modelClass, new ClusterVO($cluster));

            $genericIndexer->setBatchSize($batchSize);
            $genericIndexer->setLimit($limit);
            $genericIndexer->indexAll($indexableVO);
            $label = $this->getModelLabel($modelClass);
            $this->info("✅ All {$label} indexed successfully (batch: {$batchSize}, limit: ".($limit ?? 'unlimited').')');
        }

        return ExitCode::SUCCESS;
    }
}
