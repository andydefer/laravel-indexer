<?php

// tests/Unit/Rules/SearchableFieldsRuleTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Unit\Rules;

use AndyDefer\LaravelIndexer\Rules\SearchableFieldsRule;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestPharmacy;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

final class SearchableFieldsRuleTest extends IntegrationTestCase
{
    private function makeRule(string $modelClass): SearchableFieldsRule
    {
        return new SearchableFieldsRule($modelClass);
    }

    private function runValidation(ValidationRule $rule, mixed $value): ?string
    {
        $failedMessage = null;

        $rule->validate('fields', $value, function ($message) use (&$failedMessage) {
            $failedMessage = $message;
        });

        return $failedMessage;
    }

    // ============================================================
    // TESTS AVEC TEST_USER
    // ============================================================

    public function test_validates_valid_fields_for_test_user(): void
    {
        $rule = $this->makeRule(TestUser::class);
        $result = $this->runValidation($rule, ['name', 'email', 'description']);

        $this->assertNull($result);
    }

    public function test_validates_partial_fields_for_test_user(): void
    {
        $rule = $this->makeRule(TestUser::class);
        $result = $this->runValidation($rule, ['name', 'email']);

        $this->assertNull($result);
    }

    public function test_fails_with_invalid_field_for_test_user(): void
    {
        $rule = $this->makeRule(TestUser::class);
        $result = $this->runValidation($rule, ['name', 'invalid_field']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Invalid field(s): invalid_field', $result);
        $this->assertStringContainsString('Allowed fields: name, email, description', $result);
    }

    // ============================================================
    // TESTS AVEC TEST_PHARMACY
    // ============================================================

    public function test_validates_valid_fields_for_test_pharmacy(): void
    {
        $rule = $this->makeRule(TestPharmacy::class);
        $result = $this->runValidation($rule, ['name', 'address', 'city', 'postal_code', 'phone', 'email']);

        $this->assertNull($result);
    }

    public function test_fails_with_invalid_field_for_test_pharmacy(): void
    {
        $rule = $this->makeRule(TestPharmacy::class);
        $result = $this->runValidation($rule, ['name', 'invalid_field']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Invalid field(s): invalid_field', $result);
        $this->assertStringContainsString('address', $result);
        $this->assertStringContainsString('city', $result);
    }

    // ============================================================
    // TESTS AVEC TEST_PRODUCT
    // ============================================================

    public function test_validates_valid_fields_for_test_product(): void
    {
        $rule = $this->makeRule(TestProduct::class);
        $result = $this->runValidation($rule, ['name', 'reference', 'description']);

        $this->assertNull($result);
    }

    public function test_fails_with_invalid_field_for_test_product(): void
    {
        $rule = $this->makeRule(TestProduct::class);
        $result = $this->runValidation($rule, ['name', 'invalid_field']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Invalid field(s): invalid_field', $result);
        $this->assertStringContainsString('reference', $result);
        $this->assertStringContainsString('description', $result);
    }

    // ============================================================
    // TESTS AVEC TEST_DOCTOR
    // ============================================================

    public function test_validates_valid_fields_for_test_doctor(): void
    {
        $rule = $this->makeRule(TestDoctor::class);
        $result = $this->runValidation($rule, ['first_name', 'last_name', 'specialty', 'email']);

        $this->assertNull($result);
    }

    public function test_fails_with_invalid_field_for_test_doctor(): void
    {
        $rule = $this->makeRule(TestDoctor::class);
        $result = $this->runValidation($rule, ['first_name', 'invalid_field']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Invalid field(s): invalid_field', $result);
        $this->assertStringContainsString('last_name', $result);
        $this->assertStringContainsString('specialty', $result);
        $this->assertStringContainsString('email', $result);
        $this->assertStringContainsString('phone', $result);
        $this->assertStringContainsString('address', $result);
        $this->assertStringContainsString('city', $result);
        $this->assertStringContainsString('postal_code', $result);
        $this->assertStringContainsString('hospital', $result);
    }

    // ============================================================
    // TESTS DE CAS LIMITES
    // ============================================================
    public function test_fails_when_value_is_not_array(): void
    {
        $rule = $this->makeRule(TestUser::class);
        $result = $this->runValidation($rule, 'not an array');

        $this->assertNotNull($result);
        $this->assertEquals('The :attribute must be an array.', $result);
    }

    public function test_fails_when_model_class_does_not_exist(): void
    {
        $rule = new SearchableFieldsRule('NonExistentClass');
        $result = $this->runValidation($rule, ['name']);

        $this->assertNotNull($result);
        $this->assertEquals('The specified model class does not exist.', $result);
    }

    public function test_fails_when_model_class_does_not_implement_indexable(): void
    {
        $rule = new SearchableFieldsRule(\stdClass::class);
        $result = $this->runValidation($rule, ['name']);

        $this->assertNotNull($result);
        $this->assertEquals('The specified model class must implement Indexable.', $result);
    }

    public function test_passes_when_value_is_empty_array(): void
    {
        $rule = $this->makeRule(TestUser::class);
        $result = $this->runValidation($rule, []);

        $this->assertNull($result);
    }

    public function test_passes_when_fields_are_duplicated(): void
    {
        $rule = $this->makeRule(TestUser::class);
        $result = $this->runValidation($rule, ['name', 'name', 'email']);

        $this->assertNull($result);
    }

    // ============================================================
    // TESTS D'INTÉGRATION AVEC LARAVEL VALIDATOR
    // ============================================================

    public function test_rule_works_with_laravel_validator(): void
    {
        $validator = Validator::make(
            ['fields' => ['name', 'email']],
            ['fields' => [new SearchableFieldsRule(TestUser::class)]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_rule_fails_with_laravel_validator_for_invalid_field(): void
    {
        $validator = Validator::make(
            ['fields' => ['name', 'invalid_field']],
            ['fields' => [new SearchableFieldsRule(TestUser::class)]]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString(
            'Invalid field(s): invalid_field',
            $validator->errors()->first('fields')
        );
    }

    public function test_rule_fails_with_laravel_validator_for_non_array(): void
    {
        $validator = Validator::make(
            ['fields' => 'not an array'],
            ['fields' => [new SearchableFieldsRule(TestUser::class)]]
        );

        $this->assertFalse($validator->passes());
        $this->assertEquals(
            'The fields must be an array.',
            $validator->errors()->first('fields')
        );
    }

    public function test_rule_works_with_different_models(): void
    {
        $validator = Validator::make(
            ['fields' => ['first_name', 'last_name']],
            ['fields' => [new SearchableFieldsRule(TestDoctor::class)]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_rule_shows_allowed_fields_in_error_message(): void
    {
        $validator = Validator::make(
            ['fields' => ['name', 'invalid']],
            ['fields' => [new SearchableFieldsRule(TestUser::class)]]
        );

        $this->assertFalse($validator->passes());
        $error = $validator->errors()->first('fields');

        $this->assertStringContainsString('Invalid field(s): invalid', $error);
        $this->assertStringContainsString('name, email, description', $error);
    }
}
