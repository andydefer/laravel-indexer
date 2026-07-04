<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use InvalidArgumentException;

/**
 * Value Object représentant un cluster pour le regroupement de données.
 *
 * Format: "key1-value1|key2-value2|key3-value3"
 *
 * @example
 * $cluster = new ClusterVO('model-User|tenant-company_123|env-production');
 * $cluster->getValue(); // StrictAssociative(['model' => 'User', 'tenant' => 'company_123', 'env' => 'production'])
 * $cluster->get('model'); // 'User'
 * $cluster->has('tenant'); // true
 */
final class ClusterVO extends AbstractValueObject
{
    private const SEPARATOR_PAIR = '-';

    private const SEPARATOR_GROUP = '|';

    /** @var array<string, string> */
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
                sprintf('Invalid cluster format. Expected "key-value", got "%s"', $value)
            );
        }

        $pairs = explode(self::SEPARATOR_GROUP, $value);
        foreach ($pairs as $pair) {
            if (! str_contains($pair, self::SEPARATOR_PAIR)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid pair format. Expected "key-value", got "%s"', $pair)
                );
            }

            [$key, $val] = explode(self::SEPARATOR_PAIR, $pair, 2);

            if (empty($key)) {
                throw new InvalidArgumentException('Cluster key cannot be empty');
            }
        }
    }

    private function parse(string $value): void
    {
        $pairs = explode(self::SEPARATOR_GROUP, $value);
        foreach ($pairs as $pair) {
            [$key, $val] = explode(self::SEPARATOR_PAIR, $pair, 2);
            $this->parsed[$key] = $val;
        }
    }

    /**
     * Récupère une valeur par sa clé
     */
    public function get(string $key): ?string
    {
        return $this->parsed[$key] ?? null;
    }

    /**
     * Vérifie si une clé existe
     */
    public function has(string $key): bool
    {
        return isset($this->parsed[$key]);
    }

    /**
     * Récupère toutes les paires clé-valeur sous forme de string
     */
    public function getValue(): string
    {
        return $this->value;

    }

    /**
     * Récupère toutes les paires clé-valeur sous forme de tableau
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->parsed;
    }

    /**
     * Ajoute une paire clé-valeur (retourne une nouvelle instance)
     */
    public function with(string $key, string $value): self
    {
        $newPairs = $this->parsed;
        $newPairs[$key] = $value;

        $newValue = $this->buildFromPairs($newPairs);

        return new self($newValue);
    }

    /**
     * Supprime une clé (retourne une nouvelle instance)
     */
    public function without(string $key): self
    {
        $newPairs = $this->parsed;
        unset($newPairs[$key]);

        $newValue = $this->buildFromPairs($newPairs);

        return new self($newValue);
    }

    /**
     * Vérifie si le cluster contient toutes les clés demandées
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
     * Vérifie si le cluster contient au moins une des clés demandées
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
     * Construit la chaîne à partir d'un tableau de paires
     *
     * @param  array<string, string>  $pairs
     */
    private function buildFromPairs(array $pairs): string
    {
        $parts = [];
        foreach ($pairs as $key => $value) {
            $parts[] = $key.self::SEPARATOR_PAIR.$value;
        }

        return implode(self::SEPARATOR_GROUP, $parts);
    }

    /**
     * Retourne la représentation en tableau
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->parsed;
    }
}
