<?php

// src/Helpers/IndexableFieldHelper.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Helpers;

use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\Contracts\Services\IndexableFieldDiscoveryServiceInterface;
use InvalidArgumentException;

/**
 * Helper for retrieving searchable fields from Indexable models.
 *
 * Uses AST analysis to extract fields from getIndexableData() method.
 * No model instantiation is performed.
 */
final class IndexableFieldHelper
{
    private static ?IndexableFieldDiscoveryServiceInterface $discoveryService = null;

    private static function getDiscoveryService(): IndexableFieldDiscoveryServiceInterface
    {
        if (self::$discoveryService === null) {
            self::$discoveryService = app(IndexableFieldDiscoveryServiceInterface::class);
        }

        return self::$discoveryService;
    }

    /**
     * Get the searchable fields from an Indexable model using AST analysis.
     *
     * @param  class-string  $modelClass
     * @return array<string>
     *
     * @throws InvalidArgumentException
     */
    public static function getSearchableFields(string $modelClass): array
    {
        self::validateIndexable($modelClass);

        return self::getDiscoveryService()->discoverFields($modelClass);
    }

    /**
     * Get validation rules for fields.
     *
     * @param  class-string  $modelClass
     * @return array<string, array<int, string|array<int, string>>>
     *
     * @throws InvalidArgumentException
     */
    public static function getFieldsRule(string $modelClass): array
    {
        $fields = self::getSearchableFields($modelClass);

        return [
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['string', 'in:'.implode(',', $fields)],
        ];
    }

    /**
     * Get default fields for a model.
     *
     * @param  class-string  $modelClass
     * @return array<string>
     *
     * @throws InvalidArgumentException
     */
    public static function getDefaultFields(string $modelClass): array
    {
        $fields = self::getSearchableFields($modelClass);

        return array_slice($fields, 0, 3);
    }

    /**
     * Validate that a class implements Indexable interface.
     *
     * @param  class-string  $modelClass
     *
     * @throws InvalidArgumentException
     */
    private static function validateIndexable(string $modelClass): void
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException(sprintf(
                'Class "%s" does not exist.',
                $modelClass
            ));
        }

        if (! is_subclass_of($modelClass, Indexable::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class "%s" must implement Indexable.',
                $modelClass
            ));
        }
    }
}
