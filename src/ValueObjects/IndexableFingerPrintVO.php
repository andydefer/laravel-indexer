<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object représentant un fingerprint d'entité indexable.
 *
 * Format: "{namespace}|{id}" où namespace est le FQCN avec des backslashes.
 *
 * @example
 * $fingerprint = new IndexableFingerPrintVO('App\Models\User|123');
 * $fingerprint->getId(); // '123'
 * $fingerprint->getNamespace(); // 'App\Models\User'
 * $fingerprint->getValue(); // 'App\Models\User|123'
 */
final class IndexableFingerPrintVO extends AbstractValueObject
{
    private const SEPARATOR = '|';

    private string $id;

    private string $namespace;

    public function __construct(public readonly string $value)
    {
        $this->validate($value);
        $this->parse($value);
    }

    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new InvalidArgumentException('IndexableFingerPrint cannot be empty');
        }

        if (! str_contains($value, self::SEPARATOR)) {
            throw new InvalidArgumentException(
                sprintf('Invalid format. Expected "{namespace}|{id}", got "%s"', $value)
            );
        }

        $parts = explode(self::SEPARATOR, $value, 2);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException(
                sprintf('Invalid format. Expected "{namespace}|{id}", got "%s"', $value)
            );
        }

        [$namespace, $id] = $parts;

        if (empty($id)) {
            throw new InvalidArgumentException('ID cannot be empty');
        }

        if (empty($namespace)) {
            throw new InvalidArgumentException('Namespace cannot be empty');
        }

        $this->namespace = $namespace;
        $this->id = $id;
    }

    private function parse(string $value): void
    {
        [$this->namespace, $this->id] = explode(self::SEPARATOR, $value, 2);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getValue(): string
    {
        return $this->namespace.self::SEPARATOR.$this->id;
    }

    public function belongsTo(string $namespace): bool
    {
        return $this->namespace === $namespace;
    }

    public function belongsToAny(array $namespaces): bool
    {
        return in_array($this->namespace, $namespaces, true);
    }

    public static function fromParts(string $namespace, string $id): self
    {
        return new self($namespace.self::SEPARATOR.$id);
    }
}
