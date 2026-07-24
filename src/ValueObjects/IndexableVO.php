<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Associative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use InvalidArgumentException;

final class IndexableVO extends AbstractValueObject
{
    private readonly string $modelClass;

    private readonly ClusterVO $cluster;

    public function __construct(
        string $modelClass,
        ClusterVO $cluster,
    ) {
        $this->validate($modelClass);
        $this->modelClass = $modelClass;
        $this->cluster = $cluster;
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

    public function getCluster(): ClusterVO
    {
        return $this->cluster;
    }

    public function getClusterType(): string
    {
        return $this->cluster->getValue();
    }

    public function getValue(): Associative
    {
        return Associative::from([
            'modelClass' => $this->modelClass,
            'cluster' => $this->cluster,
        ]);
    }
}
