<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object représentant un cluster pour le regroupement de données.
 *
 * Format: "key1:value1|key2:value2,value3|key3:value4,value5,value6"
 */
final class ClusterVO extends AbstractValueObject
{
    private const SEPARATOR_PAIR = ':';

    private const SEPARATOR_GROUP = '|';

    private const SEPARATOR_VALUES = ',';

    /** @var array<string, string[]> */
    private array $parsed = [];

    public function __construct(public readonly string $value)
    {
        if ($value === '') {
            $this->parsed = [];

            return;
        }

        $this->validate($value);
        $this->parse($value);
    }

    private function validate(string $value): void
    {
        if (! str_contains($value, self::SEPARATOR_PAIR)) {
            throw new InvalidArgumentException(
                sprintf('Invalid cluster format. Expected "key:value", got "%s"', $value)
            );
        }

        $pairs = explode(self::SEPARATOR_GROUP, $value);
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue;
            }

            if (! str_contains($pair, self::SEPARATOR_PAIR)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid pair format. Expected "key:value", got "%s"', $pair)
                );
            }

            $parts = explode(self::SEPARATOR_PAIR, $pair, 2);

            if (count($parts) !== 2) {
                throw new InvalidArgumentException(
                    sprintf('Invalid pair format. Expected "key:value", got "%s"', $pair)
                );
            }

            [$key, $values] = $parts;

            if (empty($key)) {
                throw new InvalidArgumentException('Cluster key cannot be empty');
            }

            // ✅ '0' est une valeur valide
            if ($values === '' || $values === null) {
                throw new InvalidArgumentException(
                    sprintf('Cluster values cannot be empty for key "%s"', $key)
                );
            }

            $valueList = explode(self::SEPARATOR_VALUES, $values);
            foreach ($valueList as $val) {
                // ✅ '0' est une valeur valide
                if ($val === '' || $val === null || trim($val) === '') {
                    throw new InvalidArgumentException(
                        sprintf('Empty value not allowed for key "%s"', $key)
                    );
                }
            }
        }
    }

    private function parse(string $value): void
    {
        $pairs = explode(self::SEPARATOR_GROUP, $value);
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue;
            }

            $parts = explode(self::SEPARATOR_PAIR, $pair, 2);
            if (count($parts) === 2) {
                [$key, $values] = $parts;
                $valueList = explode(self::SEPARATOR_VALUES, $values);
                $this->parsed[$key] = array_map('trim', $valueList);
            }
        }
    }

    public function get(string $key): array
    {
        return $this->parsed[$key] ?? [];
    }

    public function getFirst(string $key): ?string
    {
        $values = $this->get($key);

        return $values[0] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->parsed[$key]);
    }

    public function contains(string $key, string $value): bool
    {
        if (! $this->has($key)) {
            return false;
        }

        return in_array($value, $this->parsed[$key], true);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function all(): array
    {
        return $this->parsed;
    }

    public function with(string $key, string $value): self
    {
        return $this->withIf(true, $key, $value);
    }

    public function withIf(bool $condition, string $key, string $value): self
    {
        if (! $condition) {
            return $this;
        }

        $newPairs = $this->parsed;
        if (! isset($newPairs[$key])) {
            $newPairs[$key] = [];
        }
        if (! in_array($value, $newPairs[$key], true)) {
            $newPairs[$key][] = $value;
        }

        $newValue = $this->buildFromPairs($newPairs);

        return new self($newValue);
    }

    /**
     * Ajoute une paire clé-valeur avec une valeur par défaut si la valeur est null ou une chaîne vide.
     * false, 0, '0' sont considérés comme des valeurs valides.
     */
    public function withDefault(string $key, mixed $value, string $default): self
    {
        if ($value === null || $value === '') {
            return $this->with($key, $default);
        }

        if (is_bool($value)) {
            return $this->with($key, $value ? 'true' : 'false');
        }

        // ✅ Gérer 0, '0', 0.0
        if ($value === 0 || $value === '0' || $value === 0.0) {
            return $this->with($key, '0');
        }

        return $this->with($key, (string) $value);
    }

    /**
     * Ajoute une paire clé-valeur en fonction d'un booléen (ternaire).
     */
    public function withTernary(string $key, bool $condition, string $trueValue, string $falseValue): self
    {
        return $this->with($key, $condition ? $trueValue : $falseValue);
    }

    public function withMany(string $key, array $values): self
    {
        return $this->withManyIf(true, $key, $values);
    }

    public function withManyIf(bool $condition, string $key, array $values): self
    {
        if (! $condition || empty($values)) {
            return $this;
        }

        $newPairs = $this->parsed;
        if (! isset($newPairs[$key])) {
            $newPairs[$key] = [];
        }
        foreach ($values as $value) {
            if (! in_array($value, $newPairs[$key], true)) {
                $newPairs[$key][] = $value;
            }
        }

        $newValue = $this->buildFromPairs($newPairs);

        return new self($newValue);
    }

    public function without(string $key, ?string $value = null): self
    {
        if (! $this->has($key)) {
            return $this;
        }

        $newPairs = $this->parsed;

        if ($value === null) {
            unset($newPairs[$key]);
        } else {
            $newPairs[$key] = array_filter(
                $newPairs[$key],
                fn ($v) => $v !== $value
            );
            if (empty($newPairs[$key])) {
                unset($newPairs[$key]);
            }
        }

        $newValue = $this->buildFromPairs($newPairs);

        return new self($newValue);
    }

    public function hasAll(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! $this->has($key)) {
                return false;
            }
        }

        return true;
    }

    public function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
    }

    public static function make(string $key, string $value): self
    {
        return new self($key.self::SEPARATOR_PAIR.$value);
    }

    public static function fromPairs(array $pairs): self
    {
        if (empty($pairs)) {
            return new self('');
        }

        $parts = [];
        foreach ($pairs as $key => $value) {
            if (is_array($value)) {
                $parts[] = $key.self::SEPARATOR_PAIR.implode(self::SEPARATOR_VALUES, $value);
            } else {
                $parts[] = $key.self::SEPARATOR_PAIR.$value;
            }
        }

        return new self(implode(self::SEPARATOR_GROUP, $parts));
    }

    /**
     * Ajoute une paire clé-valeur si la valeur n'est pas vide.
     * false, 0, '0' sont considérés comme des valeurs valides.
     */
    public function whenNotEmpty(string $key, mixed $value): self
    {
        if ($value === null || $value === '' || $value === []) {
            return $this;
        }

        if (is_bool($value)) {
            return $this->with($key, $value ? 'true' : 'false');
        }

        // ✅ Gérer 0, '0', 0.0
        if ($value === 0 || $value === '0' || $value === 0.0) {
            return $this->with($key, '0');
        }

        return $this->with($key, (string) $value);
    }

    public function whenNotNull(string $key, mixed $value): self
    {
        if ($value === null) {
            return $this;
        }

        if ($value === '') {
            return $this;
        }

        return $this->with($key, (string) $value);
    }

    public function whenKeyExists(string $key, array $array, string $arrayKey): self
    {
        if (! isset($array[$arrayKey])) {
            return $this;
        }

        $value = $array[$arrayKey];

        if ($value === null || $value === '') {
            return $this;
        }

        return $this->with($key, (string) $value);
    }

    public function whenArrayNotEmpty(string $key, array $values, string $separator = ','): self
    {
        if (empty($values)) {
            return $this;
        }

        return $this->with($key, implode($separator, $values));
    }

    public function whenNumeric(string $key, mixed $value): self
    {
        if ($value === null) {
            return $this;
        }

        if (! is_numeric($value)) {
            return $this;
        }

        if ($value === '') {
            return $this;
        }

        return $this->with($key, (string) $value);
    }

    public function whenBool(string $key, mixed $value): self
    {
        if ($value === null) {
            return $this;
        }

        if (! is_bool($value)) {
            return $this;
        }

        return $this->with($key, $value ? 'true' : 'false');
    }

    private function buildFromPairs(array $pairs): string
    {
        $parts = [];
        foreach ($pairs as $key => $values) {
            $parts[] = $key.self::SEPARATOR_PAIR.implode(self::SEPARATOR_VALUES, $values);
        }

        return implode(self::SEPARATOR_GROUP, $parts);
    }

    public function toArray(): array
    {
        return $this->parsed;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
