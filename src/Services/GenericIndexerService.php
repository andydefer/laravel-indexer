<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services;

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\Repositories\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Generic indexer service implementation.
 *
 * Provides a high-level API for indexing, refreshing, deleting, and
 * querying Eloquent models that implement the Indexable contract.
 *
 * This service handles the orchestration between the underlying indexer
 * and the repository, managing batch processing and fingerprint-based
 * operations.
 */
final class GenericIndexerService implements GenericIndexerInterface
{
    /**
     * The number of models to process per batch.
     */
    private int $batchSize;

    /**
     * The maximum number of models to process, or null for no limit.
     */
    private ?int $limit = null;

    public function __construct(
        private readonly IndexerInterface $indexer,
        private readonly IndexedDocumentRepositoryInterface $documentRepository,
        private readonly IndexerConfigInterface $config,
    ) {
        $this->batchSize = $this->config->getBatchSize();
    }

    // ============================================================
    // Public Methods
    // ============================================================

    /**
     * {@inheritDoc}
     */
    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = $batchSize;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
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

        $fingerprint = IndexableFingerprintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );

        if ($this->documentRepository->existsByFingerPrint($fingerprint)) {
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

                $fingerprint = IndexableFingerprintVO::fromParts(
                    $model->getMorphClass(),
                    (string) $model->getKey()
                );

                if ($this->documentRepository->existsByFingerPrint($fingerprint)) {
                    $this->documentRepository->deleteByFingerPrint($fingerprint);
                }

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
        $fingerprint = IndexableFingerprintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );

        $this->indexer->delete($fingerprint);
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
        $this->documentRepository->deleteByNamespace($modelClass);
    }

    /**
     * {@inheritDoc}
     */
    public function refresh(Model&Indexable $model): void
    {
        $fingerprint = IndexableFingerprintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );

        $this->indexer->delete($fingerprint);

        if ($model->shouldBeIndexed()) {
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
        return $this->documentRepository->countByNamespace($modelClass);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(Model&Indexable $model): bool
    {
        $fingerprint = IndexableFingerprintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );

        return $this->documentRepository->existsByFingerPrint($fingerprint);
    }

    /**
     * {@inheritDoc}
     */
    public function existsById(string $modelClass, int $id): bool
    {
        $fingerprint = IndexableFingerprintVO::fromParts(
            $modelClass,
            (string) $id
        );

        return $this->documentRepository->existsByFingerPrint($fingerprint);
    }
}
