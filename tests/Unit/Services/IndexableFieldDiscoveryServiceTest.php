<?php

// tests/Unit/Services/IndexableFieldDiscoveryServiceTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Unit\Services;

use AndyDefer\LaravelIndexer\Contracts\Services\IndexableFieldDiscoveryServiceInterface;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestPharmacy;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;

final class IndexableFieldDiscoveryServiceTest extends IntegrationTestCase
{
    private IndexableFieldDiscoveryServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(IndexableFieldDiscoveryServiceInterface::class);
    }

    // ============================================================
    // TESTS AVEC LES FIXTURES MODELS
    // ============================================================

    public function test_discover_fields_from_test_user_model(): void
    {
        $fields = $this->service->discoverFields(TestUser::class);

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('description', $fields);
    }

    public function test_discover_fields_from_test_pharmacy_model(): void
    {
        $fields = $this->service->discoverFields(TestPharmacy::class);

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('address', $fields);
        $this->assertContains('city', $fields);
        $this->assertContains('postal_code', $fields);
        $this->assertContains('phone', $fields);
        $this->assertContains('email', $fields);
    }

    public function test_discover_fields_from_test_product_model(): void
    {
        $fields = $this->service->discoverFields(TestProduct::class);

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('reference', $fields);
        $this->assertContains('description', $fields);
    }

    public function test_discover_fields_from_test_doctor_model(): void
    {
        $fields = $this->service->discoverFields(TestDoctor::class);

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

    // ============================================================
    // TESTS DE CAS LIMITES
    // ============================================================

    public function test_discover_fields_from_non_existent_class_returns_empty(): void
    {
        $fields = $this->service->discoverFields('NonExistentClass');

        $this->assertEmpty($fields);
    }

    public function test_discover_fields_from_class_without_file_returns_empty(): void
    {
        $fields = $this->service->discoverFields(\stdClass::class);

        $this->assertEmpty($fields);
    }

    // ============================================================
    // TESTS DE DISCOVER_FIELDS_FOR_MANY
    // ============================================================

    public function test_discover_fields_for_many_models(): void
    {
        $classes = [
            TestUser::class,
            TestPharmacy::class,
            TestProduct::class,
        ];

        $result = $this->service->discoverFieldsForMany($classes);

        $this->assertArrayHasKey(TestUser::class, $result);
        $this->assertArrayHasKey(TestPharmacy::class, $result);
        $this->assertArrayHasKey(TestProduct::class, $result);

        $this->assertNotEmpty($result[TestUser::class]);
        $this->assertNotEmpty($result[TestPharmacy::class]);
        $this->assertNotEmpty($result[TestProduct::class]);

        $this->assertContains('name', $result[TestUser::class]);
        $this->assertContains('name', $result[TestPharmacy::class]);
        $this->assertContains('name', $result[TestProduct::class]);
    }

    public function test_discover_fields_for_many_with_doctor_model(): void
    {
        $classes = [
            TestUser::class,
            TestDoctor::class,
        ];

        $result = $this->service->discoverFieldsForMany($classes);

        $this->assertArrayHasKey(TestUser::class, $result);
        $this->assertArrayHasKey(TestDoctor::class, $result);

        $this->assertContains('name', $result[TestUser::class]);
        $this->assertContains('first_name', $result[TestDoctor::class]);
        $this->assertContains('last_name', $result[TestDoctor::class]);
        $this->assertContains('specialty', $result[TestDoctor::class]);
    }
}
