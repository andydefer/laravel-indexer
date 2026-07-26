<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services;

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class GenericIndexerService implements GenericIndexerInterface
{
    private int $batchSize;

    private ?int $limit = null;

    public function __construct(
        private readonly IndexerInterface $indexer,
        private readonly IndexedDocumentRepositoryInterface $documentRepository,
        private readonly IndexerConfigInterface $config,
    ) {
        $this->batchSize = $this->config->getBatchSize();
    }

    // ============================================================
    // PUBLIC METHODS
    // ============================================================

    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = $batchSize;

        return $this;
    }

    public function setLimit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function index(Model&Indexable $model): void
    {
        if (! $model->shouldBeIndexed()) {
            return;
        }

        $cluster = $model->getIndexableCluster();
        $record = IndexableRecordFactory::convert($model, $cluster);

        $fingerPrint = IndexableFingerPrintVO::fromParts($model->getMorphClass(), (string) $model->getKey());
        if ($this->documentRepository->existsByFingerPrint($fingerPrint)) {
            $this->indexer->refresh($record);
        } else {
            $this->indexer->index($record);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function indexById(string $modelClass, int $id): void
    {
        /** @var Model&Indexable $model */
        $model = $modelClass::find($id);

        if (! $model) {
            throw new ModelNotFoundException("Model with ID {$id} not found");
        }

        $this->index($model);
    }

    /**
     * {@inheritDoc}
     */
    public function indexAll(string $modelClass): void
    {
        $processed = 0;
        $limit = $this->limit;

        /** @var Model&Indexable $modelClass */
        $modelClass::chunk($this->batchSize, function ($models) use (&$processed, $limit) {
            $records = new IndexableRecordCollection;

            foreach ($models as $model) {
                if ($limit !== null && $processed >= $limit) {
                    if ($records->isNotEmpty()) {
                        $this->indexer->indexMany($records);
                    }

                    return false;
                }

                if (! $model->shouldBeIndexed()) {
                    continue;
                }

                $fingerPrint = IndexableFingerPrintVO::fromParts($model->getMorphClass(), (string) $model->getKey());

                if ($this->documentRepository->existsByFingerPrint($fingerPrint)) {
                    $this->documentRepository->deleteByFingerPrint($fingerPrint);
                }

                // ✅ Utiliser le cluster dynamique du modèle (OBLIGATOIRE)
                $cluster = $model->getIndexableCluster();
                $record = IndexableRecordFactory::convert($model, $cluster);
                $records->add($record);
                $processed++;
            }

            if ($records->isNotEmpty()) {
                $this->indexer->indexMany($records);
            }

            return true;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function reindexAll(string $modelClass): void
    {
        $this->deleteAll($modelClass);
        $this->indexAll($modelClass);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Model&Indexable $model): void
    {
        $fingerPrint = IndexableFingerPrintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );

        $this->indexer->delete($fingerPrint);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteById(string $modelClass, int $id): void
    {
        /** @var Model&Indexable $model */
        $model = $modelClass::find($id);

        if (! $model) {
            throw new ModelNotFoundException("Model with ID {$id} not found");
        }

        $this->delete($model);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteAll(string $modelClass): void
    {
        $namespace = $this->getNamespace($modelClass);
        $this->documentRepository->deleteByNamespace($namespace);
    }

    /**
     * {@inheritDoc}
     */
    public function refresh(Model&Indexable $model): void
    {
        $fingerPrint = IndexableFingerPrintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );

        $this->indexer->delete($fingerPrint);

        if ($model->shouldBeIndexed()) {
            // ✅ Utiliser le cluster dynamique du modèle (OBLIGATOIRE)
            $cluster = $model->getIndexableCluster();
            $record = IndexableRecordFactory::convert($model, $cluster);
            $this->indexer->refresh($record);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function refreshById(string $modelClass, int $id): void
    {
        /** @var Model&Indexable $model */
        $model = $modelClass::find($id);

        if (! $model) {
            throw new ModelNotFoundException("Model with ID {$id} not found");
        }

        $this->refresh($model);
    }

    /**
     * {@inheritDoc}
     */
    public function countIndexed(string $modelClass): int
    {
        $namespace = $this->getNamespace($modelClass);

        return $this->documentRepository->countByNamespace($namespace);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(Model&Indexable $model): bool
    {
        $fingerPrint = IndexableFingerPrintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );

        return $this->documentRepository->existsByFingerPrint($fingerPrint);
    }

    /**
     * {@inheritDoc}
     */
    public function existsById(string $modelClass, int $id): bool
    {
        $fingerPrint = IndexableFingerPrintVO::fromParts(
            $modelClass,
            (string) $id
        );

        return $this->documentRepository->existsByFingerPrint($fingerPrint);
    }

    // ============================================================
    // PRIVATE METHODS
    // ============================================================

    private function getNamespace(string $modelClass): string
    {
        return str_replace('\\', '.', $modelClass);
    }
}
