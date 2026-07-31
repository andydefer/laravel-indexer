<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object representing an indexable entity fingerprint.
 *
 * Format: "{namespace}|{id}" where namespace is the FQCN with backslashes.
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

    /**
     * Validates the fingerprint format.
     *
     * @param  string  $value  The raw fingerprint string
     *
     * @throws InvalidArgumentException If the format is invalid
     */
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

    /**
     * Parses the fingerprint string into namespace and ID components.
     *
     * @param  string  $value  The raw fingerprint string
     */
    private function parse(string $value): void
    {
        [$this->namespace, $this->id] = explode(self::SEPARATOR, $value, 2);
    }

    /**
     * Returns the entity ID.
     *
     * @return string The entity ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the namespace.
     *
     * @return string The namespace (e.g., 'App\Models\User')
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Returns the full fingerprint string.
     *
     * @return string The full fingerprint
     */
    public function getValue(): string
    {
        return $this->namespace.self::SEPARATOR.$this->id;
    }

    /**
     * Checks if the fingerprint belongs to the given namespace.
     *
     * @param  string  $namespace  The namespace to check
     * @return bool True if the fingerprint belongs to the namespace
     */
    public function belongsTo(string $namespace): bool
    {
        return $this->namespace === $namespace;
    }

    /**
     * Checks if the fingerprint belongs to any of the given namespaces.
     *
     * @param  string[]  $namespaces  The namespaces to check
     * @return bool True if the fingerprint belongs to any of the namespaces
     */
    public function belongsToAny(array $namespaces): bool
    {
        return in_array($this->namespace, $namespaces, true);
    }

    /**
     * Creates a new instance from namespace and ID parts.
     *
     * @param  string  $namespace  The namespace
     * @param  string  $id  The entity ID
     * @return self A new fingerprint instance
     */
    public static function fromParts(string $namespace, string $id): self
    {
        return new self($namespace.self::SEPARATOR.$id);
    }
}
