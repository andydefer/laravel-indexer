<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object représentant un cluster pour le regroupement de données.
 *
 * Format: "key1:value1|key2:value2,value3|key3:value4,value5,value6"
 *
 * @example
 * $cluster = new ClusterVO('model:User|tenant:company_abc,company_xyz|env:production|category:electronics,music');
 * $cluster->get('model');    // ['User']
 * $cluster->get('tenant');   // ['company_abc', 'company_xyz']
 * $cluster->get('category'); // ['electronics', 'music']
 * $cluster->has('tenant');   // true
 * $cluster->has('unknown');  // false
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
        $this->validate($value);
        $this->parse($value);
    }

    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Cluster cannot be empty');
        }

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

            if (empty($values)) {
                throw new InvalidArgumentException(
                    sprintf('Cluster values cannot be empty for key "%s"', $key)
                );
            }

            // Valider que les valeurs ne sont pas vides
            $valueList = explode(self::SEPARATOR_VALUES, $values);
            foreach ($valueList as $val) {
                if (empty(trim($val))) {
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

    /**
     * Récupère les valeurs par leur clé.
     * Retourne toujours un tableau, même pour une seule valeur.
     *
     * @return string[]
     */
    public function get(string $key): array
    {
        return $this->parsed[$key] ?? [];
    }

    /**
     * Vérifie si une clé existe.
     */
    public function has(string $key): bool
    {
        return isset($this->parsed[$key]);
    }

    /**
     * Vérifie si une clé contient une valeur spécifique.
     */
    public function contains(string $key, string $value): bool
    {
        if (! $this->has($key)) {
            return false;
        }

        return in_array($value, $this->parsed[$key], true);
    }

    /**
     * Récupère toutes les paires clé-valeur sous forme de string.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Récupère toutes les paires clé-valeur sous forme de tableau.
     *
     * @return array<string, string[]>
     */
    public function all(): array
    {
        return $this->parsed;
    }

    /**
     * Ajoute une valeur à une clé (retourne une nouvelle instance).
     */
    public function with(string $key, string $value): self
    {
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
     * Ajoute plusieurs valeurs à une clé (retourne une nouvelle instance).
     *
     * @param  string[]  $values
     */
    public function withMany(string $key, array $values): self
    {
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

    /**
     * Supprime une valeur d'une clé (retourne une nouvelle instance).
     */
    public function without(string $key, ?string $value = null): self
    {
        if (! $this->has($key)) {
            return $this;
        }

        $newPairs = $this->parsed;

        if ($value === null) {
            // Supprimer toute la clé
            unset($newPairs[$key]);
        } else {
            // Supprimer une valeur spécifique
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

    /**
     * Vérifie si le cluster contient toutes les clés demandées.
     */
    public function hasAll(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! $this->has($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie si le cluster contient au moins une des clés demandées.
     */
    public function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Construit la chaîne à partir d'un tableau de paires.
     *
     * @param  array<string, string[]>  $pairs
     */
    private function buildFromPairs(array $pairs): string
    {
        $parts = [];
        foreach ($pairs as $key => $values) {
            $parts[] = $key.self::SEPARATOR_PAIR.implode(self::SEPARATOR_VALUES, $values);
        }

        return implode(self::SEPARATOR_GROUP, $parts);
    }

    /**
     * Retourne la représentation en tableau.
     *
     * @return array<string, string[]>
     */
    public function toArray(): array
    {
        return $this->parsed;
    }
}
