<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use InvalidArgumentException;

/**
 * Value Object representing a search query.
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

    /**
     * Validates the search query format.
     *
     * @param  string  $value  The raw search query string
     *
     * @throws InvalidArgumentException If the format is invalid
     */
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

    /**
     * Parses the search query string into a structured array.
     *
     * @param  string  $value  The raw search query string
     */
    private function parse(string $value): void
    {
        $parts = explode(self::SEPARATOR_GROUP, $value);

        foreach ($parts as $part) {
            [$ngram, $fields] = explode(self::SEPARATOR_NGRAM_FIELD, $part, 2);
            $this->parsed[$ngram] = explode(',', $fields);
        }
    }

    /**
     * Returns the parsed query as a StrictAssociative array.
     *
     * @return StrictAssociative<string, string[]> The parsed query
     */
    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from($this->parsed);
    }

    /**
     * Returns all n-grams in the query.
     *
     * @return string[] The list of n-grams
     */
    public function getNgrams(): array
    {
        return array_keys($this->parsed);
    }

    /**
     * Returns the fields associated with a specific n-gram.
     *
     * @param  mixed  $ngram  The n-gram to look up
     * @return string[] The list of fields for the n-gram
     *
     * @throws InvalidArgumentException If the ngram is not a string
     */
    public function getFieldsForNgram(mixed $ngram): array
    {
        if (! is_string($ngram)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot look up n-gram with non-string value. Got: %s. '
                .'N-grams must be strings. '
                .'If you are trying to index a numeric value (like price, age, quantity), '
                .'move it to getIndexableCluster() instead of getIndexableData(). '
                .'Example: return ClusterVO::from([\'price\' => $this->price]);',
                get_debug_type($ngram)
            ));
        }

        return $this->parsed[$ngram] ?? [];
    }

    /**
     * Checks if the query contains a specific n-gram.
     *
     * @param  string  $ngram  The n-gram to check
     * @return bool True if the n-gram exists
     */
    public function hasNgram(string $ngram): bool
    {
        return isset($this->parsed[$ngram]);
    }

    /**
     * Checks if a specific field is searched for a given n-gram.
     *
     * @param  string  $ngram  The n-gram to check
     * @param  string  $field  The field to check
     * @return bool True if the field is associated with the n-gram
     */
    public function hasFieldForNgram(string $ngram, string $field): bool
    {
        if (! $this->hasNgram($ngram)) {
            return false;
        }

        return in_array($field, $this->parsed[$ngram], true);
    }

    /**
     * Checks if the query contains a specific n-gram with all given fields.
     *
     * @param  string  $ngram  The n-gram to check
     * @param  string[]  $fields  The fields that must be present
     * @return bool True if the n-gram exists with all fields
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
     * Returns the total number of search conditions (n-grams).
     *
     * @return int The number of n-grams
     */
    public function count(): int
    {
        return count($this->parsed);
    }

    /**
     * Returns all unique fields used in the query.
     *
     * @return string[] The list of unique fields
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
     * Checks if the query is empty.
     *
     * @return bool True if the query has no n-grams
     */
    public function isEmpty(): bool
    {
        return empty($this->parsed);
    }

    /**
     * Returns the raw query string.
     *
     * @return string The raw query string
     */
    public function getRaw(): string
    {
        return $this->value;
    }
}
