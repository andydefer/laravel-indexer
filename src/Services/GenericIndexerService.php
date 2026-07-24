<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services;

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class GenericIndexerService implements GenericIndexerInterface
{
    public function __construct(
        private readonly IndexerInterface $indexer,
        private readonly IndexedDocumentRepositoryInterface $documentRepository,
        private int $batchSize = 50,
    ) {}

    public function setBatchSize(int $batchSize): self
    {
        $this->batchSize = $batchSize;

        return $this;
    }

    private function getNamespace(string $modelClass): string
    {
        return str_replace('\\', '.', $modelClass);
    }

    private function buildCluster(ClusterVO $cluster): ClusterVO
    {
        return $cluster;
    }

    public function index(IndexableVO $indexableVO, int $id): void
    {
        $modelClass = $indexableVO->getModelClass();

        /** @var Model&Indexable $model */
        $model = $modelClass::find($id);

        if (! $model) {
            throw new ModelNotFoundException("Model with ID {$id} not found");
        }

        if (! $model->shouldBeIndexed()) {
            return;
        }

        $cluster = $this->buildCluster($indexableVO->getCluster());
        $record = IndexableRecordFactory::convert($model, $cluster);
        $this->indexer->index($record);
    }

    public function indexAll(IndexableVO $indexableVO): void
    {
        $modelClass = $indexableVO->getModelClass();

        $modelClass::where('is_active', true)
            ->chunk($this->batchSize, function ($models) use ($indexableVO) {
                $records = new IndexableRecordCollection;

                foreach ($models as $model) {
                    if (! $model->shouldBeIndexed()) {
                        continue;
                    }

                    $cluster = $this->buildCluster($indexableVO->getCluster());
                    $records->add(IndexableRecordFactory::convert($model, $cluster));
                }

                if ($records->isNotEmpty()) {
                    $this->indexer->indexMany($records);
                }
            });
    }

    public function reindexAll(IndexableVO $indexableVO): void
    {
        $this->deleteAll($indexableVO);
        $this->indexAll($indexableVO);
    }

    public function delete(IndexableVO $indexableVO, int $id): void
    {
        $modelClass = $indexableVO->getModelClass();

        /** @var Model&Indexable $model */
        $model = $modelClass::find($id);

        if (! $model) {
            throw new ModelNotFoundException("Model with ID {$id} not found");
        }

        $fingerPrint = IndexableFingerPrintVO::fromParts(
            $indexableVO->getModelClass(),
            (string) $model->getKey()
        );

        $this->indexer->delete($fingerPrint);
    }

    public function deleteAll(IndexableVO $indexableVO): void
    {
        $namespace = $this->getNamespace($indexableVO->getModelClass());
        $this->documentRepository->deleteByNamespace($namespace);
    }

    public function refresh(IndexableVO $indexableVO, int $id): void
    {
        $modelClass = $indexableVO->getModelClass();

        /** @var Model&Indexable $model */
        $model = $modelClass::find($id);

        if (! $model) {
            throw new ModelNotFoundException("Model with ID {$id} not found");
        }

        $fingerPrint = IndexableFingerPrintVO::fromParts(
            $indexableVO->getModelClass(),
            (string) $model->getKey()
        );

        $this->indexer->delete($fingerPrint);

        if ($model->shouldBeIndexed()) {
            $cluster = $this->buildCluster($indexableVO->getCluster());
            $record = IndexableRecordFactory::convert($model, $cluster);
            $this->indexer->refresh($record);
        }
    }

    public function countIndexed(IndexableVO $indexableVO): int
    {
        $namespace = $this->getNamespace($indexableVO->getModelClass());

        return $this->documentRepository->countByNamespace($namespace);
    }

    public function exists(IndexableVO $indexableVO, int $id): bool
    {
        $fingerPrint = IndexableFingerPrintVO::fromParts(
            $indexableVO->getModelClass(),
            (string) $id
        );

        return $this->documentRepository->existsByFingerPrint($fingerPrint);
    }
}
