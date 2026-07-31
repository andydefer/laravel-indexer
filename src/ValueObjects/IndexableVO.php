<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\Repository\Exceptions\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Value Object representing an indexable entity.
 *
 * Holds a model class name and its ID, providing methods to retrieve
 * the model instance and to validate that the class implements Indexable.
 */
final class IndexableVO extends AbstractValueObject
{
    private readonly string $modelClass;

    private readonly int|string $id;

    public function __construct(
        string $modelClass,
        int|string $id,
    ) {
        $this->validate($modelClass);
        $this->modelClass = $modelClass;
        $this->id = $id;
    }

    /**
     * Validates that the class exists and implements Indexable.
     *
     * @param  string  $modelClass  The model class name
     *
     * @throws InvalidArgumentException If the class is invalid
     */
    private function validate(string $modelClass): void
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException("Class {$modelClass} does not exist");
        }

        if (! in_array(Indexable::class, class_implements($modelClass), true)) {
            throw new InvalidArgumentException(
                sprintf('Class %s must implement %s', $modelClass, Indexable::class)
            );
        }
    }

    /**
     * Returns the model class name.
     *
     * @return string The fully-qualified model class name
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * Returns the entity ID.
     *
     * @return int|string The entity ID
     */
    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * Retrieves the model instance.
     *
     * @return Model&Indexable The model instance
     *
     * @throws ModelNotFoundException If the model is not found
     */
    public function getInstance(): Model&Indexable
    {
        /** @var Model&Indexable $model */
        $model = $this->modelClass::find($this->id);

        if (! $model) {
            throw new ModelNotFoundException(
                sprintf('Model %s with ID %s not found', $this->modelClass, $this->id)
            );
        }

        return $model;
    }

    /**
     * Returns the value as an associative array.
     *
     * @return StrictAssociative The value object as an associative array
     */
    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from([
            'model_class' => $this->modelClass,
            'id' => $this->id,
        ]);
    }
}
