<?php

// tests/Unit/Helpers/IndexableFieldHelperTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Unit\Helpers;

use AndyDefer\LaravelIndexer\Helpers\IndexableFieldHelper;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestPharmacy;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use InvalidArgumentException;

final class IndexableFieldHelperTest extends IntegrationTestCase
{
    // ============================================================
    // TESTS GET_SEARCHABLE_FIELDS
    // ============================================================

    public function test_get_searchable_fields_from_test_user_model(): void
    {
        $fields = IndexableFieldHelper::getSearchableFields(TestUser::class);

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('description', $fields);
    }

    public function test_get_searchable_fields_from_test_pharmacy_model(): void
    {
        $fields = IndexableFieldHelper::getSearchableFields(TestPharmacy::class);

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('address', $fields);
        $this->assertContains('city', $fields);
        $this->assertContains('postal_code', $fields);
        $this->assertContains('phone', $fields);
        $this->assertContains('email', $fields);
    }

    public function test_get_searchable_fields_from_test_product_model(): void
    {
        $fields = IndexableFieldHelper::getSearchableFields(TestProduct::class);

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('reference', $fields);
        $this->assertContains('description', $fields);
    }

    public function test_get_searchable_fields_from_test_doctor_model(): void
    {
        $fields = IndexableFieldHelper::getSearchableFields(TestDoctor::class);

        $this->assertNotEmpty($fields);
        $this->assertContains('first_name', $fields);
        $this->assertContains('last_name', $fields);
        $this->assertContains('specialty', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('phone', $fields);
        $this->assertContains('address', $fields);
        $this->assertContains('city', $fields);
        $this->assertContains('postal_code', $fields);
        $this->assertContains('hospital', $fields);
    }

    public function test_get_searchable_fields_throws_exception_for_non_existent_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "NonExistentClass" does not exist');

        IndexableFieldHelper::getSearchableFields('NonExistentClass');
    }

    public function test_get_searchable_fields_throws_exception_for_non_indexable_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement Indexable');

        IndexableFieldHelper::getSearchableFields(\stdClass::class);
    }

    // ============================================================
    // TESTS GET_FIELDS_RULE
    // ============================================================

    public function test_get_fields_rule_from_test_user_model(): void
    {
        $rules = IndexableFieldHelper::getFieldsRule(TestUser::class);

        $this->assertArrayHasKey('fields', $rules);
        $this->assertArrayHasKey('fields.*', $rules);

        $this->assertEquals('sometimes', $rules['fields'][0]);
        $this->assertEquals('array', $rules['fields'][1]);

        $this->assertEquals('string', $rules['fields.*'][0]);
        $this->assertStringStartsWith('in:', $rules['fields.*'][1]);

        $inRule = $rules['fields.*'][1];
        $fields = explode(',', substr($inRule, 3));
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('description', $fields);
    }

    public function test_get_fields_rule_from_test_pharmacy_model(): void
    {
        $rules = IndexableFieldHelper::getFieldsRule(TestPharmacy::class);

        $inRule = $rules['fields.*'][1];
        $fields = explode(',', substr($inRule, 3));

        $this->assertContains('name', $fields);
        $this->assertContains('address', $fields);
        $this->assertContains('city', $fields);
        $this->assertContains('postal_code', $fields);
        $this->assertContains('phone', $fields);
        $this->assertContains('email', $fields);
    }

    public function test_get_fields_rule_throws_exception_for_non_indexable_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IndexableFieldHelper::getFieldsRule(\stdClass::class);
    }

    // ============================================================
    // TESTS GET_DEFAULT_FIELDS
    // ============================================================

    public function test_get_default_fields_from_test_user_model(): void
    {
        $fields = IndexableFieldHelper::getDefaultFields(TestUser::class);

        $this->assertCount(3, $fields);
        $this->assertEquals(['name', 'email', 'description'], $fields);
    }

    public function test_get_default_fields_from_test_pharmacy_model(): void
    {
        $fields = IndexableFieldHelper::getDefaultFields(TestPharmacy::class);

        $this->assertCount(3, $fields);
        $this->assertEquals(['name', 'address', 'city'], $fields);
    }

    public function test_get_default_fields_from_test_product_model(): void
    {
        $fields = IndexableFieldHelper::getDefaultFields(TestProduct::class);

        $this->assertCount(3, $fields);
        $this->assertEquals(['name', 'reference', 'description'], $fields);
    }

    public function test_get_default_fields_from_test_doctor_model(): void
    {
        $fields = IndexableFieldHelper::getDefaultFields(TestDoctor::class);

        $this->assertCount(3, $fields);
        $this->assertEquals(['first_name', 'last_name', 'specialty'], $fields);
    }

    public function test_get_default_fields_throws_exception_for_non_indexable_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        IndexableFieldHelper::getDefaultFields(\stdClass::class);
    }

    // ============================================================
    // TESTS D'INTÉGRATION
    // ============================================================

    public function test_fields_rule_contains_valid_fields(): void
    {
        $rules = IndexableFieldHelper::getFieldsRule(TestUser::class);

        $inRule = $rules['fields.*'][1];
        $fields = explode(',', substr($inRule, 3));

        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('description', $fields);
    }

    public function test_all_models_return_unique_fields(): void
    {
        $userFields = IndexableFieldHelper::getSearchableFields(TestUser::class);
        $pharmacyFields = IndexableFieldHelper::getSearchableFields(TestPharmacy::class);
        $productFields = IndexableFieldHelper::getSearchableFields(TestProduct::class);
        $doctorFields = IndexableFieldHelper::getSearchableFields(TestDoctor::class);

        $this->assertNotEquals($userFields, $pharmacyFields);
        $this->assertNotEquals($pharmacyFields, $productFields);
        $this->assertNotEquals($productFields, $doctorFields);

        $this->assertContains('name', $userFields);
        $this->assertContains('name', $pharmacyFields);
        $this->assertContains('name', $productFields);
        $this->assertContains('first_name', $doctorFields);
        $this->assertContains('last_name', $doctorFields);
    }

    public function test_default_fields_returns_first_three_fields(): void
    {
        $fields = IndexableFieldHelper::getDefaultFields(TestUser::class);
        $this->assertCount(3, $fields);

        $fields = IndexableFieldHelper::getDefaultFields(TestPharmacy::class);
        $this->assertCount(3, $fields);

        $fields = IndexableFieldHelper::getDefaultFields(TestProduct::class);
        $this->assertCount(3, $fields);

        $fields = IndexableFieldHelper::getDefaultFields(TestDoctor::class);
        $this->assertCount(3, $fields);
    }
}
