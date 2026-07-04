<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object représentant un fingerprint d'entité indexable.
 *
 * Format: "{namespace}|{id}"
 * où namespace est le FQCN avec les \ remplacés par .
 *
 * @example
 * $id = new IndexableFingerPrintVO('App.Models.User|123');
 * $id->getId(); // '123'
 * $id->getNamespace(); // 'App.Models.User'
 * $id->getValue(); // 'App.Models.User|123'
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

        if (str_contains($namespace, '\\')) {
            throw new InvalidArgumentException(
                sprintf('Namespace cannot contain "\\". Got "%s"', $namespace)
            );
        }
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
        return $this->value;
    }

    public function belongsTo(string $namespace): bool
    {
        return $this->namespace === $namespace;
    }

    public function belongsToAny(array $namespaces): bool
    {
        return in_array($this->namespace, $namespaces, true);
    }

    public function getOriginalNamespace(): string
    {
        return str_replace('.', '\\', $this->namespace);
    }
}
