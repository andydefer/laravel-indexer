<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object représentant un cluster.
 *
 * STOCKAGE (dans le modèle) : "key1:value1|key2:value2|key3:value3"
 * RECHERCHE (dans la requête) : "key1:value1|key2:value2|key3:value3@AND", "@OR" ou "@NOT"
 *
 * Le mode @AND/@OR/@NOT est OPTIONNEL pour le stockage, OBLIGATOIRE pour la recherche.
 *
 * Caractères autorisés :
 * - Clés : a-z, A-Z, 0-9, _ uniquement
 * - Valeurs : tous les caractères (libre) mais les caractères réservés sont remplacés par '.'
 */
final class ClusterVO extends AbstractValueObject
{
    private const SEPARATOR_PAIR = ':';

    private const SEPARATOR_GROUP = '|';

    private const SEPARATOR_MODE = '@';

    private const MODE_AND = 'AND';

    private const MODE_OR = 'OR';

    private const MODE_NOT = 'NOT';

    /** @var array<string, string> */
    private array $parsed = [];

    private ?string $mode = null;

    public function __construct(public readonly string $value)
    {
        if ($value === '') {
            throw new InvalidArgumentException('Cluster value cannot be empty');
        }

        $this->validate($value);
        $this->parse($value);
    }

    /**
     * Nettoie une valeur en remplaçant les caractères réservés par '.'
     */
    private function sanitizeValue(string $value): string
    {
        // Remplacer les séparateurs réservés par '.'
        return str_replace(
            [self::SEPARATOR_PAIR, self::SEPARATOR_GROUP, self::SEPARATOR_MODE],
            '.',
            $value
        );
    }

    private function validate(string $value): void
    {
        $hasMode = str_contains($value, self::SEPARATOR_MODE);

        if ($hasMode) {
            $parts = explode(self::SEPARATOR_MODE, $value);
            if (count($parts) !== 2) {
                throw new InvalidArgumentException(
                    sprintf('Invalid cluster format. Expected "key:value|key:value@AND", got "%s"', $value)
                );
            }

            [$clusterPart, $mode] = $parts;

            if ($mode !== self::MODE_AND && $mode !== self::MODE_OR && $mode !== self::MODE_NOT) {
                throw new InvalidArgumentException(
                    sprintf('Invalid mode. Expected "AND", "OR" or "NOT", got "%s"', $mode)
                );
            }

            $this->mode = $mode;
            $clusterValue = $clusterPart;
        } else {
            $clusterValue = $value;
        }

        if (empty($clusterValue)) {
            throw new InvalidArgumentException('Cluster cannot be empty');
        }

        $pairs = explode(self::SEPARATOR_GROUP, $clusterValue);
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue;
            }

            if (! str_contains($pair, self::SEPARATOR_PAIR)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid pair format. Expected "key:value", got "%s"', $pair)
                );
            }

            $pairParts = explode(self::SEPARATOR_PAIR, $pair, 2);
            if (count($pairParts) !== 2) {
                throw new InvalidArgumentException(
                    sprintf('Invalid pair format. Expected "key:value", got "%s"', $pair)
                );
            }

            [$key, $val] = $pairParts;

            if (empty($key)) {
                throw new InvalidArgumentException('Cluster key cannot be empty');
            }

            if ($val === '' || $val === null) {
                throw new InvalidArgumentException(
                    sprintf('Cluster value cannot be empty for key "%s"', $key)
                );
            }

            // ✅ Seulement vérifier que la clé n'est pas vide
            // Pas de validation stricte sur les caractères de la clé
        }
    }

    private function parse(string $value): void
    {
        if (str_contains($value, self::SEPARATOR_MODE)) {
            $parts = explode(self::SEPARATOR_MODE, $value);
            $clusterPart = $parts[0];
        } else {
            $clusterPart = $value;
        }

        $pairs = explode(self::SEPARATOR_GROUP, $clusterPart);
        foreach ($pairs as $pair) {
            if (empty($pair)) {
                continue;
            }

            $pairParts = explode(self::SEPARATOR_PAIR, $pair, 2);
            if (count($pairParts) === 2) {
                [$key, $val] = $pairParts;
                // ✅ Nettoyer la valeur en remplaçant les caractères réservés
                $this->parsed[$key] = $this->sanitizeValue(trim($val));
            }
        }
    }

    public function get(string $key): ?string
    {
        return $this->parsed[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->parsed[$key]);
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function hasMode(): bool
    {
        return $this->mode !== null;
    }

    public function isAnd(): bool
    {
        return $this->mode === self::MODE_AND;
    }

    public function isOr(): bool
    {
        return $this->mode === self::MODE_OR;
    }

    public function isNot(): bool
    {
        return $this->mode === self::MODE_NOT;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getClusterPart(): string
    {
        if (str_contains($this->value, self::SEPARATOR_MODE)) {
            $parts = explode(self::SEPARATOR_MODE, $this->value);

            return $parts[0];
        }

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

        // ✅ Nettoyer la valeur
        $sanitizedValue = $this->sanitizeValue($value);

        $newPairs = $this->parsed;
        $newPairs[$key] = $sanitizedValue;

        $clusterPart = $this->buildClusterPart($newPairs);
        $newValue = $this->mode !== null ? $clusterPart.self::SEPARATOR_MODE.$this->mode : $clusterPart;

        return new self($newValue);
    }

    public function without(string $key): self
    {
        if (! $this->has($key)) {
            return $this;
        }

        $newPairs = $this->parsed;
        unset($newPairs[$key]);

        if (empty($newPairs)) {
            throw new InvalidArgumentException('Cluster cannot be empty after removal');
        }

        $clusterPart = $this->buildClusterPart($newPairs);
        $newValue = $this->mode !== null ? $clusterPart.self::SEPARATOR_MODE.$this->mode : $clusterPart;

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

    public static function make(string $key, string $value, ?string $mode = null): self
    {
        $cluster = $key.self::SEPARATOR_PAIR.$value;

        if ($mode !== null && ($mode === self::MODE_AND || $mode === self::MODE_OR || $mode === self::MODE_NOT)) {
            $cluster .= self::SEPARATOR_MODE.$mode;
        }

        return new self($cluster);
    }

    public function withMode(string $mode): self
    {
        if ($mode !== self::MODE_AND && $mode !== self::MODE_OR && $mode !== self::MODE_NOT) {
            throw new InvalidArgumentException(
                sprintf('Invalid mode. Expected "AND", "OR" or "NOT", got "%s"', $mode)
            );
        }

        $clusterPart = $this->getClusterPart();

        return new self($clusterPart.self::SEPARATOR_MODE.$mode);
    }

    public function toAnd(): self
    {
        return $this->withMode(self::MODE_AND);
    }

    public function toOr(): self
    {
        return $this->withMode(self::MODE_OR);
    }

    public function toNot(): self
    {
        return $this->withMode(self::MODE_NOT);
    }

    public function whenNotEmpty(string $key, mixed $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        if (is_bool($value)) {
            return $this->with($key, $value ? 'true' : 'false');
        }

        if ($value === 0 || $value === '0' || $value === 0.0) {
            return $this->with($key, '0');
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

    public function whenNotNull(string $key, mixed $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        return $this->with($key, (string) $value);
    }

    public function whenNumeric(string $key, mixed $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        if (! is_numeric($value)) {
            return $this;
        }

        return $this->with($key, (string) $value);
    }

    public function withTernary(string $key, bool $condition, string $trueValue, string $falseValue): self
    {
        return $this->with($key, $condition ? $trueValue : $falseValue);
    }

    /**
     * Ajoute des paires pour chaque valeur d'un tableau.
     * Format : "prefix_{value}:true"
     *
     * @param  string  $prefix  Préfixe de la clé (ex: 'lang_')
     * @param  array<string>  $values  Liste des valeurs
     * @param  string  $suffix  Suffixe de la clé (optionnel)
     */
    public function withCases(string $prefix, array $values, string $suffix = ''): self
    {
        foreach ($values as $value) {
            // ✅ Nettoyer la valeur
            $sanitizedValue = $this->sanitizeValue($value);
            $key = $prefix.$sanitizedValue.$suffix;
            $this->parsed[$key] = 'true';
        }

        $clusterPart = $this->buildClusterPart($this->parsed);
        $newValue = $this->mode !== null ? $clusterPart.self::SEPARATOR_MODE.$this->mode : $clusterPart;

        return new self($newValue);
    }

    /**
     * Ajoute des paires pour chaque valeur d'un enum (UnitEnum).
     * Utilise le nom du case en minuscules.
     * Format : "prefix_{name}:true"
     *
     * @param  string  $prefix  Préfixe de la clé (ex: 'role_')
     * @param  class-string<\UnitEnum>  $enumClass  Classe de l'enum
     * @param  string  $suffix  Suffixe de la clé (optionnel)
     */
    public function withEnum(string $prefix, string $enumClass, string $suffix = ''): self
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException(sprintf('Enum class "%s" does not exist', $enumClass));
        }

        $values = array_map(
            fn ($case) => strtolower($case->name),
            $enumClass::cases()
        );

        return $this->withCases($prefix, $values, $suffix);
    }

    /**
     * Ajoute des paires pour les valeurs d'un enum (BackedEnum ou UnitEnum).
     * Pour BackedEnum, utilise la valeur (value) en minuscules.
     * Pour UnitEnum, utilise le nom (name) en minuscules.
     *
     * @param  string  $prefix  Préfixe de la clé
     * @param  class-string<\UnitEnum>  $enumClass  Classe de l'enum
     * @param  string  $suffix  Suffixe de la clé (optionnel)
     */
    public function withEnumValues(string $prefix, string $enumClass, string $suffix = ''): self
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException(sprintf('Enum class "%s" does not exist', $enumClass));
        }

        $values = array_map(
            function ($case) {
                $value = $case->value ?? $case->name;

                return strtolower($value);
            },
            $enumClass::cases()
        );

        return $this->withCases($prefix, $values, $suffix);
    }

    private function buildClusterPart(array $pairs): string
    {
        $parts = [];
        foreach ($pairs as $key => $value) {
            $parts[] = $key.self::SEPARATOR_PAIR.$value;
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
