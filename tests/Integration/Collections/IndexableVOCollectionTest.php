<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Collections;

use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestPharmacy;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class IndexableVOCollectionTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private IndexableVOCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = new IndexableVOCollection;
    }

    // ============================================================
    // TESTS D'ADDITION
    // ============================================================

    public function test_can_add_items(): void
    {
        $this->collection->add(new IndexableVO(TestUser::class, 1));
        $this->collection->add(new IndexableVO(TestUser::class, 2));

        $this->assertCount(2, $this->collection);
    }

    public function test_can_add_multiple_items(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestUser::class, 2),
            new IndexableVO(TestDoctor::class, 3)
        );

        $this->assertCount(3, $this->collection);
    }

    // ============================================================
    // TESTS DE getIds()
    // ============================================================

    public function test_can_get_ids(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestUser::class, 2),
            new IndexableVO(TestDoctor::class, 3)
        );

        $ids = $this->collection->getIds();

        $this->assertCount(3, $ids);
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
    }

    // ============================================================
    // TESTS DE getModelClasses()
    // ============================================================

    public function test_can_get_model_classes(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestUser::class, 2),
            new IndexableVO(TestDoctor::class, 3)
        );

        $classes = $this->collection->getModelClasses();

        $this->assertCount(3, $classes);
        $this->assertContains(TestUser::class, $classes);
        $this->assertContains(TestDoctor::class, $classes);
    }

    // ============================================================
    // TESTS DE getModelInstances()
    // ============================================================

    public function test_get_model_instances_returns_only_existing_models(): void
    {
        $user = TestUser::create(['name' => 'John', 'email' => 'john@test.com', 'is_active' => true]);

        $this->collection->add(
            new IndexableVO(TestUser::class, $user->id),
            new IndexableVO(TestUser::class, 999),
            new IndexableVO(TestDoctor::class, 888)
        );

        $instances = $this->collection->getModelInstances();

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(TestUser::class, $instances[0]);
        $this->assertEquals('John', $instances[0]->name);
    }

    public function test_get_model_instances_returns_empty_when_no_models_found(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 999),
            new IndexableVO(TestDoctor::class, 888)
        );

        $instances = $this->collection->getModelInstances();

        $this->assertCount(0, $instances);
    }

    public function test_get_model_instances_with_multiple_classes_and_missing_ids(): void
    {
        $user = TestUser::create(['name' => 'John', 'email' => 'john@test.com', 'is_active' => true]);

        $this->collection->add(
            new IndexableVO(TestUser::class, $user->id),
            new IndexableVO(TestUser::class, 999),
            new IndexableVO(TestDoctor::class, 888),
            new IndexableVO(TestPharmacy::class, 777)
        );

        $instances = $this->collection->getModelInstances();

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(TestUser::class, $instances[0]);
        $this->assertEquals('John', $instances[0]->name);
    }

    // ============================================================
    // TESTS DE filterByModelClass()
    // ============================================================

    public function test_can_filter_by_model_class(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestUser::class, 2),
            new IndexableVO(TestDoctor::class, 3),
            new IndexableVO(TestDoctor::class, 4)
        );

        $filtered = $this->collection->filterByModelClass(TestUser::class);

        $this->assertCount(2, $filtered);
        $this->assertInstanceOf(IndexableVOCollection::class, $filtered);
        $this->assertEquals(TestUser::class, $filtered->first()->getModelClass());
        $this->assertEquals(TestUser::class, $filtered->last()->getModelClass());
    }

    // ============================================================
    // TESTS DE filterByModelClasses()
    // ============================================================

    public function test_can_filter_by_multiple_model_classes(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestPharmacy::class, 2),
            new IndexableVO(TestDoctor::class, 3),
            new IndexableVO(TestProduct::class, 4)
        );

        $filtered = $this->collection->filterByModelClasses([TestUser::class, TestPharmacy::class]);

        $this->assertCount(2, $filtered);
        $this->assertEquals(TestUser::class, $filtered->first()->getModelClass());
        $this->assertEquals(TestPharmacy::class, $filtered->last()->getModelClass());
    }

    // ============================================================
    // TESTS DE containsId()
    // ============================================================

    public function test_can_check_if_contains_id(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestUser::class, 2)
        );

        $this->assertTrue($this->collection->containsId(1));
        $this->assertTrue($this->collection->containsId(2));
        $this->assertFalse($this->collection->containsId(3));
    }

    // ============================================================
    // TESTS DE containsModelClass()
    // ============================================================

    public function test_can_check_if_contains_model_class(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestDoctor::class, 2)
        );

        $this->assertTrue($this->collection->containsModelClass(TestUser::class));
        $this->assertTrue($this->collection->containsModelClass(TestDoctor::class));
        $this->assertFalse($this->collection->containsModelClass(TestPharmacy::class));
    }

    // ============================================================
    // TESTS DE findById()
    // ============================================================

    public function test_can_find_by_id(): void
    {
        $item1 = new IndexableVO(TestUser::class, 1);
        $item2 = new IndexableVO(TestUser::class, 2);

        $this->collection->add($item1, $item2);

        $found = $this->collection->findById(1);

        $this->assertNotNull($found);
        $this->assertSame($item1, $found);
        $this->assertEquals(1, $found->getId());

        $notFound = $this->collection->findById(3);
        $this->assertNull($notFound);
    }

    // ============================================================
    // TESTS DE filterByModelClassAndIds()
    // ============================================================

    public function test_can_filter_by_model_class_and_ids(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestUser::class, 2),
            new IndexableVO(TestUser::class, 3),
            new IndexableVO(TestDoctor::class, 4),
            new IndexableVO(TestDoctor::class, 5)
        );

        $filtered = $this->collection->filterByModelClassAndIds(TestUser::class, [1, 3]);

        $this->assertCount(2, $filtered);
        $this->assertEquals(TestUser::class, $filtered->first()->getModelClass());
        $this->assertEquals(1, $filtered->first()->getId());
        $this->assertEquals(3, $filtered->last()->getId());
    }

    public function test_filter_by_model_class_and_ids_returns_empty_when_no_match(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestUser::class, 2)
        );

        $filtered = $this->collection->filterByModelClassAndIds(TestUser::class, [3, 4]);

        $this->assertCount(0, $filtered);
    }

    // ============================================================
    // TESTS DE groupByModelClass()
    // ============================================================

    public function test_can_group_by_model_class(): void
    {
        $this->collection->add(
            new IndexableVO(TestUser::class, 1),
            new IndexableVO(TestUser::class, 2),
            new IndexableVO(TestDoctor::class, 3),
            new IndexableVO(TestDoctor::class, 4)
        );

        $groups = $this->collection->groupByModelClass();

        $this->assertCount(2, $groups);
        $this->assertArrayHasKey(TestUser::class, $groups);
        $this->assertArrayHasKey(TestDoctor::class, $groups);
        $this->assertCount(2, $groups[TestUser::class]);
        $this->assertCount(2, $groups[TestDoctor::class]);
        $this->assertInstanceOf(IndexableVOCollection::class, $groups[TestUser::class]);
        $this->assertInstanceOf(IndexableVOCollection::class, $groups[TestDoctor::class]);
    }
}
