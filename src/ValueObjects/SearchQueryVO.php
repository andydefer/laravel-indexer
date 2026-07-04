<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use InvalidArgumentException;

/**
 * Value Object représentant une requête de recherche.
 *
 * Format: "ngram1=field1,field2|ngram2=field3|ngram3=field1,field4"
 *
 * @example
 * $query = new SearchQueryVO('john=name,description|doe=name|admin=role');
 * $query->getValue(); // StrictAssociative(['john' => ['name', 'description'], 'doe' => ['name'], 'admin' => ['role']])
 * $query->getNgrams(); // ['john', 'doe', 'admin']
 * $query->getFieldsForNgram('john'); // ['name', 'description']
 */
final class SearchQueryVO extends AbstractValueObject
{
    private const SEPARATOR_GROUP = '|';

    private const SEPARATOR_NGRAM_FIELD = '=';

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
            throw new InvalidArgumentException('Search query cannot be empty');
        }

        $parts = explode(self::SEPARATOR_GROUP, $value);

        foreach ($parts as $part) {
            if (! str_contains($part, self::SEPARATOR_NGRAM_FIELD)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid format. Expected "ngram=field1,field2", got "%s"', $part)
                );
            }

            [$ngram, $fields] = explode(self::SEPARATOR_NGRAM_FIELD, $part, 2);

            if (empty($ngram)) {
                throw new InvalidArgumentException('N-gram cannot be empty');
            }

            if (empty($fields)) {
                throw new InvalidArgumentException('Fields cannot be empty');
            }

            $fieldList = explode(',', $fields);
            foreach ($fieldList as $field) {
                if (empty(trim($field))) {
                    throw new InvalidArgumentException(
                        sprintf('Field cannot be empty in "%s"', $part)
                    );
                }
            }
        }
    }

    private function parse(string $value): void
    {
        $parts = explode(self::SEPARATOR_GROUP, $value);

        foreach ($parts as $part) {
            [$ngram, $fields] = explode(self::SEPARATOR_NGRAM_FIELD, $part, 2);
            $this->parsed[$ngram] = explode(',', $fields);
        }
    }

    /**
     * Retourne la requête sous forme de StrictAssociative.
     *
     * @return StrictAssociative<string, string[]>
     */
    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from($this->parsed);
    }

    /**
     * Récupère tous les n-grams de la requête.
     *
     * @return string[]
     */
    public function getNgrams(): array
    {
        return array_keys($this->parsed);
    }

    /**
     * Récupère les champs pour un n-gram donné.
     *
     * @return string[]
     */
    public function getFieldsForNgram(string $ngram): array
    {
        return $this->parsed[$ngram] ?? [];
    }

    /**
     * Vérifie si un n-gram existe dans la requête.
     */
    public function hasNgram(string $ngram): bool
    {
        return isset($this->parsed[$ngram]);
    }

    /**
     * Vérifie si un champ est recherché pour un n-gram donné.
     */
    public function hasFieldForNgram(string $ngram, string $field): bool
    {
        if (! $this->hasNgram($ngram)) {
            return false;
        }

        return in_array($field, $this->parsed[$ngram], true);
    }

    /**
     * Vérifie si la requête contient un n-gram spécifique avec des champs.
     */
    public function contains(string $ngram, array $fields): bool
    {
        if (! $this->hasNgram($ngram)) {
            return false;
        }

        $existingFields = $this->parsed[$ngram];
        foreach ($fields as $field) {
            if (! in_array($field, $existingFields, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retourne le nombre total de conditions de recherche.
     */
    public function count(): int
    {
        return count($this->parsed);
    }

    /**
     * Retourne tous les champs uniques utilisés dans la requête.
     *
     * @return string[]
     */
    public function getAllFields(): array
    {
        $fields = [];
        foreach ($this->parsed as $fieldList) {
            foreach ($fieldList as $field) {
                if (! in_array($field, $fields, true)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Vérifie si la requête est vide.
     */
    public function isEmpty(): bool
    {
        return empty($this->parsed);
    }

    /**
     * Retourne la représentation brute.
     */
    public function getRaw(): string
    {
        return $this->value;
    }
}
