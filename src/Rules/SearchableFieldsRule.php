<?php

// src/Rules/SearchableFieldsRule.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Rules;

use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\Helpers\IndexableFieldHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SearchableFieldsRule implements ValidationRule
{
    /**
     * @param  class-string<Indexable>  $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be an array.');

            return;
        }

        if (! class_exists($this->modelClass)) {
            $fail('The specified model class does not exist.');

            return;
        }

        if (! is_subclass_of($this->modelClass, Indexable::class)) {
            $fail('The specified model class must implement Indexable.');

            return;
        }

        $allowedFields = IndexableFieldHelper::getSearchableFields($this->modelClass);

        if (empty($allowedFields)) {
            $fail('No searchable fields found for this model.');

            return;
        }

        $invalidFields = array_diff($value, $allowedFields);

        if (! empty($invalidFields)) {
            $invalidList = implode(', ', $invalidFields);
            $allowedList = implode(', ', $allowedFields);
            $fail("Invalid field(s): {$invalidList}. Allowed fields: {$allowedList}");
        }
    }
}
