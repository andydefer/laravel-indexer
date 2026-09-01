<?php

declare(strict_types=1);
// src/Contracts/Services/IndexableFieldDiscoveryServiceInterface.php

namespace AndyDefer\LaravelIndexer\Contracts\Services;

/**
 * Service for discovering indexable fields from Eloquent models using AST analysis.
 *
 * Parses the source code of models to extract field keys from their
 * getIndexableData() method without instantiating the model.
 */
interface IndexableFieldDiscoveryServiceInterface
{
    /**
     * Discover fields from a model class.
     *
     * @param  class-string  $modelClass
     * @return array<string>
     */
    public function discoverFields(string $modelClass): array;

    /**
     * Discover fields from multiple model classes.
     *
     * @param  array<class-string>  $modelClasses
     * @return array<string, array<string>>
     */
    public function discoverFieldsForMany(array $modelClasses): array;

    /**
     * Discover fields from all models in a directory.
     *
     * @param  string  $directory  The directory to scan
     * @param  string  $namespace  The base namespace of the models
     * @return array<string, array<string>>
     */
    public function discoverFieldsInDirectory(string $directory, string $namespace): array;
}
