<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for the generic indexer service.
 *
 * Provides a unified interface for indexing, refreshing, deleting, and
 * querying Eloquent models that implement the Indexable contract.
 *
 * This interface abstracts away the underlying index storage mechanism
 * and provides a clean API for managing indexed documents.
 */
interface GenericIndexerInterface
{
    /**
     * Sets the batch size for bulk indexing operations.
     *
     * This controls how many models are processed in a single database chunk.
     *
     * @param  int  $batchSize  The number of models to process per batch
     * @return self Returns the current instance for method chaining
     */
    public function setBatchSize(int $batchSize): self;

    /**
     * Sets the maximum number of models to process in an indexing operation.
     *
     * When set, only the first N models will be processed, allowing for
     * partial indexing or testing on a subset of data.
     *
     * @param  int|null  $limit  The maximum number of models to index, or null for no limit
     * @return self Returns the current instance for method chaining
     */
    public function setLimit(?int $limit): self;

    /**
     * Indexes a single model.
     *
     * If the model is already indexed, it will be updated (refreshed).
     * If not, a new index entry will be created.
     *
     * @param  Model&Indexable  $model  The model to index
     */
    public function index(Model&Indexable $model): void;

    /**
     * Indexes a model by its class and ID.
     *
     * This method retrieves the model from the database and indexes it.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @param  int  $id  The model's primary key
     */
    public function indexById(string $modelClass, int $id): void;

    /**
     * Indexes all models of a given class.
     *
     * This is a bulk operation that iterates through all models in chunks
     * and indexes each one.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     */
    public function indexAll(string $modelClass): void;

    /**
     * Reindexes all models of a given class.
     *
     * This method first deletes all existing index entries for the class,
     * then re-indexes all models from scratch.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     */
    public function reindexAll(string $modelClass): void;

    /**
     * Deletes a model from the index.
     *
     * @param  Model&Indexable  $model  The model to remove from the index
     */
    public function delete(Model&Indexable $model): void;

    /**
     * Deletes a model from the index by its class and ID.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @param  int  $id  The model's primary key
     */
    public function deleteById(string $modelClass, int $id): void;

    /**
     * Deletes all models of a given class from the index.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     */
    public function deleteAll(string $modelClass): void;

    /**
     * Refreshes a model in the index.
     *
     * This method deletes the existing index entry and creates a new one.
     * If the model should not be indexed (according to shouldBeIndexed()),
     * it will be removed from the index.
     *
     * @param  Model&Indexable  $model  The model to refresh
     */
    public function refresh(Model&Indexable $model): void;

    /**
     * Refreshes a model in the index by its class and ID.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @param  int  $id  The model's primary key
     */
    public function refreshById(string $modelClass, int $id): void;

    /**
     * Returns the number of indexed documents for a given model class.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @return int The number of indexed documents
     */
    public function countIndexed(string $modelClass): int;

    /**
     * Checks whether a model is currently indexed.
     *
     * @param  Model&Indexable  $model  The model to check
     * @return bool True if the model is indexed, false otherwise
     */
    public function exists(Model&Indexable $model): bool;

    /**
     * Checks whether a model is currently indexed by its class and ID.
     *
     * @param  string  $modelClass  The fully-qualified model class name
     * @param  int  $id  The model's primary key
     * @return bool True if the model is indexed, false otherwise
     */
    public function existsById(string $modelClass, int $id): bool;
}
