<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services;

use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class GenericIndexerServiceTest extends IntegrationTestCase
{
    private GenericIndexerInterface $genericIndexer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->genericIndexer = $this->app->make(GenericIndexerInterface::class);
    }

    public function test_index_single_document(): void
    {
        $doctor = TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        $cluster = new ClusterVO('type:doctor|specialty:cardiology');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->index($indexableVO, $doctor->id);

        $count = $this->genericIndexer->countIndexed($indexableVO);

        $this->assertEquals(1, $count);
        $this->assertTrue($this->genericIndexer->exists($indexableVO, $doctor->id));
    }

    public function test_index_skips_when_not_should_be_indexed(): void
    {
        $doctor = TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => false,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->index($indexableVO, $doctor->id);

        $count = $this->genericIndexer->countIndexed($indexableVO);

        $this->assertEquals(0, $count);
        $this->assertFalse($this->genericIndexer->exists($indexableVO, $doctor->id));
    }

    public function test_index_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model with ID 99999 not found');

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->index($indexableVO, 99999);
    }

    public function test_index_all_documents(): void
    {
        TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        TestDoctor::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'specialty' => 'Neurology',
            'email' => 'jane@hospital.com',
            'phone' => '987654321',
            'address' => '456 Oak Ave',
            'city' => 'Los Angeles',
            'postal_code' => '90001',
            'hospital' => 'City Hospital',
            'is_active' => true,
        ]);

        TestDoctor::create([
            'first_name' => 'Bob',
            'last_name' => 'Brown',
            'specialty' => 'Cardiology',
            'email' => 'bob@hospital.com',
            'phone' => '555555555',
            'address' => '789 Pine St',
            'city' => 'Chicago',
            'postal_code' => '60601',
            'hospital' => 'Community Hospital',
            'is_active' => false,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->indexAll($indexableVO);

        $count = $this->genericIndexer->countIndexed($indexableVO);

        $this->assertEquals(2, $count);
    }

    public function test_index_all_with_empty_dataset(): void
    {
        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->indexAll($indexableVO);

        $count = $this->genericIndexer->countIndexed($indexableVO);

        $this->assertEquals(0, $count);
    }

    public function test_delete_single_document(): void
    {
        $doctor = TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->index($indexableVO, $doctor->id);
        $this->assertEquals(1, $this->genericIndexer->countIndexed($indexableVO));

        $this->genericIndexer->delete($indexableVO, $doctor->id);

        $count = $this->genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(0, $count);
        $this->assertFalse($this->genericIndexer->exists($indexableVO, $doctor->id));
    }

    public function test_delete_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model with ID 99999 not found');

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->delete($indexableVO, 99999);
    }

    public function test_delete_all_documents(): void
    {
        TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        TestDoctor::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'specialty' => 'Neurology',
            'email' => 'jane@hospital.com',
            'phone' => '987654321',
            'address' => '456 Oak Ave',
            'city' => 'Los Angeles',
            'postal_code' => '90001',
            'hospital' => 'City Hospital',
            'is_active' => true,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->indexAll($indexableVO);
        $this->assertEquals(2, $this->genericIndexer->countIndexed($indexableVO));

        $this->genericIndexer->deleteAll($indexableVO);

        $count = $this->genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(0, $count);
    }

    public function test_refresh_document(): void
    {
        $doctor = TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->index($indexableVO, $doctor->id);
        $this->assertEquals(1, $this->genericIndexer->countIndexed($indexableVO));

        $this->genericIndexer->refresh($indexableVO, $doctor->id);

        $count = $this->genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(1, $count);
        $this->assertTrue($this->genericIndexer->exists($indexableVO, $doctor->id));
    }

    public function test_refresh_skips_when_not_should_be_indexed(): void
    {
        $doctor = TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->index($indexableVO, $doctor->id);
        $this->assertEquals(1, $this->genericIndexer->countIndexed($indexableVO));

        $doctor->is_active = false;
        $doctor->save();

        $this->genericIndexer->refresh($indexableVO, $doctor->id);

        $count = $this->genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(0, $count);
    }

    public function test_refresh_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model with ID 99999 not found');

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->refresh($indexableVO, 99999);
    }

    public function test_reindex_all_documents(): void
    {
        TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        TestDoctor::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'specialty' => 'Neurology',
            'email' => 'jane@hospital.com',
            'phone' => '987654321',
            'address' => '456 Oak Ave',
            'city' => 'Los Angeles',
            'postal_code' => '90001',
            'hospital' => 'City Hospital',
            'is_active' => true,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->indexAll($indexableVO);
        $this->assertEquals(2, $this->genericIndexer->countIndexed($indexableVO));

        $this->genericIndexer->reindexAll($indexableVO);

        $count = $this->genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(2, $count);
    }

    public function test_count_indexed(): void
    {
        TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        TestDoctor::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'specialty' => 'Neurology',
            'email' => 'jane@hospital.com',
            'phone' => '987654321',
            'address' => '456 Oak Ave',
            'city' => 'Los Angeles',
            'postal_code' => '90001',
            'hospital' => 'City Hospital',
            'is_active' => true,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->indexAll($indexableVO);

        $count = $this->genericIndexer->countIndexed($indexableVO);

        $this->assertEquals(2, $count);
    }

    public function test_exists_returns_true_when_indexed(): void
    {
        $doctor = TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->index($indexableVO, $doctor->id);

        $this->assertTrue($this->genericIndexer->exists($indexableVO, $doctor->id));
    }

    public function test_exists_returns_false_when_not_indexed(): void
    {
        $doctor = TestDoctor::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => false,
        ]);

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->assertFalse($this->genericIndexer->exists($indexableVO, $doctor->id));
    }

    public function test_set_batch_size(): void
    {
        for ($i = 0; $i < 25; $i++) {
            TestDoctor::create([
                'first_name' => 'Doctor '.$i,
                'last_name' => 'Test',
                'specialty' => 'Cardiology',
                'email' => 'doctor'.$i.'@hospital.com',
                'phone' => '123456789',
                'address' => '123 Main St',
                'city' => 'New York',
                'postal_code' => '10001',
                'hospital' => 'General Hospital',
                'is_active' => true,
            ]);
        }

        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(
            modelClass: TestDoctor::class,
            cluster: $cluster,
        );

        $this->genericIndexer->setBatchSize(10);
        $this->genericIndexer->indexAll($indexableVO);

        $count = $this->genericIndexer->countIndexed($indexableVO);

        $this->assertEquals(25, $count);
    }
}
