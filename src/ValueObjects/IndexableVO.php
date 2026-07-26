<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\Repository\Exceptions\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

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

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * Récupère l'instance du modèle.
     *
     *
     * @throws ModelNotFoundException
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

    public function getValue(): Associative
    {
        return Associative::from([
            'modelClass' => $this->modelClass,
            'id' => $this->id,
        ]);
    }
}
