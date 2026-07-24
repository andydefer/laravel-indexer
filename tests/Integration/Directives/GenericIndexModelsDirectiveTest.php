<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\Directives\GenericIndexModelsDirective;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestPharmacy;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class GenericIndexModelsDirectiveTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private DirectiveTestingService $service;

    private static int $doctorCounter = 0;

    private static int $pharmacyCounter = 0;

    private static int $productCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('indexer.model_indexables', [
            TestDoctor::class => 'type:doctor|status:active',
            TestPharmacy::class => 'type:pharmacy|status:active',
            TestProduct::class => 'type:product|status:published',
        ]);
        $this->app['config']->set('indexer.token_types.ngrams.min_size', 2);
        $this->app['config']->set('indexer.token_types.ngrams.max_size', 4);

        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirective(GenericIndexModelsDirective::class);
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    private function createDoctor(array $attributes = []): TestDoctor
    {
        self::$doctorCounter++;

        return TestDoctor::create(array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john_'.self::$doctorCounter.'@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ], $attributes));
    }

    private function createPharmacy(array $attributes = []): TestPharmacy
    {
        self::$pharmacyCounter++;

        return TestPharmacy::create(array_merge([
            'name' => 'Central Pharmacy '.self::$pharmacyCounter,
            'address' => '456 Oak Ave',
            'city' => 'New York',
            'postal_code' => '10001',
            'phone' => '987654321',
            'email' => 'contact_'.self::$pharmacyCounter.'@centralpharmacy.com',
            'is_active' => true,
        ], $attributes));
    }

    private function createProduct(array $attributes = []): TestProduct
    {
        self::$productCounter++;

        return TestProduct::create(array_merge([
            'name' => 'Product '.self::$productCounter,
            'reference' => 'REF-'.str_pad((string) self::$productCounter, 3, '0', STR_PAD_LEFT),
            'description' => 'Test product description '.self::$productCounter,
            'is_published' => true,
        ], $attributes));
    }

    public function test_index_all_models(): void
    {
        $this->createDoctor();
        $this->createPharmacy();
        $this->createProduct();

        $response = $this->service->run('index:models ['.TestDoctor::class.','.TestPharmacy::class.','.TestProduct::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('All '.TestDoctor::class.' indexed successfully', $response->output);
        $this->assertStringContainsString('All '.TestPharmacy::class.' indexed successfully', $response->output);
        $this->assertStringContainsString('All '.TestProduct::class.' indexed successfully', $response->output);
        $this->assertStringContainsString('new items indexed', $response->output);
    }

    public function test_index_only_doctors(): void
    {
        $this->createDoctor();
        $this->createPharmacy();
        $this->createProduct();

        $response = $this->service->run('index:models ['.TestDoctor::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('All '.TestDoctor::class.' indexed successfully', $response->output);
        $this->assertStringNotContainsString('TestPharmacy', $response->output);
        $this->assertStringNotContainsString('TestProduct', $response->output);
    }

    public function test_index_only_pharmacies(): void
    {
        $this->createDoctor();
        $this->createPharmacy();
        $this->createProduct();

        $response = $this->service->run('index:models ['.TestPharmacy::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('All '.TestPharmacy::class.' indexed successfully', $response->output);
        $this->assertStringNotContainsString('TestDoctor', $response->output);
        $this->assertStringNotContainsString('TestProduct', $response->output);
    }

    public function test_index_only_products(): void
    {
        $this->createDoctor();
        $this->createPharmacy();
        $this->createProduct();

        $response = $this->service->run('index:models ['.TestProduct::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('All '.TestProduct::class.' indexed successfully', $response->output);
        $this->assertStringNotContainsString('TestDoctor', $response->output);
        $this->assertStringNotContainsString('TestPharmacy', $response->output);
    }

    public function test_index_with_batch_size(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createDoctor();
        }

        $response = $this->service->run('index:models 10 ['.TestDoctor::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Batch size: 10', $response->output);

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);
        $count = $genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(25, $count);
    }

    public function test_index_with_limit(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createDoctor();
        }

        $response = $this->service->run('index:models _ 10 ['.TestDoctor::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Limit: 10', $response->output);

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);
        $count = $genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(10, $count);
    }

    public function test_index_with_batch_and_limit(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->createDoctor();
        }

        $response = $this->service->run('index:models 5 15 ['.TestDoctor::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Batch size: 5', $response->output);
        $this->assertStringContainsString('Limit: 15', $response->output);

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);
        $count = $genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(15, $count);
    }

    public function test_reindex_with_batch_and_limit(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createDoctor();
        }

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);
        $genericIndexer->indexAll($indexableVO);

        $response = $this->service->run('index:models 5 10 ['.TestDoctor::class.'] --reindex');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('All '.TestDoctor::class.' reindexed successfully', $response->output);
        $this->assertStringContainsString('items indexed', $response->output);

        $count = $genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(10, $count);
    }

    public function test_reindex_all_models(): void
    {
        $this->createDoctor();
        $this->createPharmacy();
        $this->createProduct();

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);

        $doctorCluster = new ClusterVO('type:doctor|status:active');
        $doctorIndexable = new IndexableVO(TestDoctor::class, $doctorCluster);
        $genericIndexer->indexAll($doctorIndexable);

        $pharmacyCluster = new ClusterVO('type:pharmacy|status:active');
        $pharmacyIndexable = new IndexableVO(TestPharmacy::class, $pharmacyCluster);
        $genericIndexer->indexAll($pharmacyIndexable);

        $productCluster = new ClusterVO('type:product|status:published');
        $productIndexable = new IndexableVO(TestProduct::class, $productCluster);
        $genericIndexer->indexAll($productIndexable);

        $response = $this->service->run('index:models ['.TestDoctor::class.','.TestPharmacy::class.','.TestProduct::class.'] --reindex');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('All '.TestDoctor::class.' reindexed successfully', $response->output);
        $this->assertStringContainsString('All '.TestPharmacy::class.' reindexed successfully', $response->output);
        $this->assertStringContainsString('All '.TestProduct::class.' reindexed successfully', $response->output);
    }

    public function test_count_indexed_models(): void
    {
        $this->createDoctor();
        $this->createPharmacy();
        $this->createProduct();

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);

        $doctorCluster = new ClusterVO('type:doctor|status:active');
        $doctorIndexable = new IndexableVO(TestDoctor::class, $doctorCluster);
        $genericIndexer->indexAll($doctorIndexable);

        $pharmacyCluster = new ClusterVO('type:pharmacy|status:active');
        $pharmacyIndexable = new IndexableVO(TestPharmacy::class, $pharmacyCluster);
        $genericIndexer->indexAll($pharmacyIndexable);

        $productCluster = new ClusterVO('type:product|status:published');
        $productIndexable = new IndexableVO(TestProduct::class, $productCluster);
        $genericIndexer->indexAll($productIndexable);

        $response = $this->service->run('index:models ['.TestDoctor::class.','.TestPharmacy::class.','.TestProduct::class.'] --count');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Indexed '.TestDoctor::class.': 1', $response->output);
        $this->assertStringContainsString('Indexed '.TestPharmacy::class.': 1', $response->output);
        $this->assertStringContainsString('Indexed '.TestProduct::class.': 1', $response->output);
        $this->assertStringContainsString('Total indexed', $response->output);
    }

    public function test_delete_all_models(): void
    {
        $this->createDoctor();
        $this->createPharmacy();
        $this->createProduct();

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);

        $doctorCluster = new ClusterVO('type:doctor|status:active');
        $doctorIndexable = new IndexableVO(TestDoctor::class, $doctorCluster);
        $genericIndexer->indexAll($doctorIndexable);

        $pharmacyCluster = new ClusterVO('type:pharmacy|status:active');
        $pharmacyIndexable = new IndexableVO(TestPharmacy::class, $pharmacyCluster);
        $genericIndexer->indexAll($pharmacyIndexable);

        $productCluster = new ClusterVO('type:product|status:published');
        $productIndexable = new IndexableVO(TestProduct::class, $productCluster);
        $genericIndexer->indexAll($productIndexable);

        $response = $this->service->run('index:models ['.TestDoctor::class.','.TestPharmacy::class.','.TestProduct::class.'] --delete');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('All '.TestDoctor::class.' deleted from index', $response->output);
        $this->assertStringContainsString('All '.TestPharmacy::class.' deleted from index', $response->output);
        $this->assertStringContainsString('All '.TestProduct::class.' deleted from index', $response->output);
        $this->assertStringContainsString('Total models cleared', $response->output);

        $doctorIndexable = new IndexableVO(TestDoctor::class, new ClusterVO('type:doctor'));
        $count = $genericIndexer->countIndexed($doctorIndexable);
        $this->assertEquals(0, $count);
    }

    public function test_no_models_specified(): void
    {
        $response = $this->service->run('index:models []');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('No models specified', $response->output);
    }

    public function test_invalid_model_not_in_config(): void
    {
        $response = $this->service->run('index:models [Invalid.Model]');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('Class \'Invalid\Model\' does not exist', $response->output);
    }

    public function test_model_not_configured_in_config(): void
    {
        $this->app['config']->set('indexer.model_indexables', [
            TestDoctor::class => 'type:doctor|status:active',
        ]);

        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        $response = $this->service->run('index:models ['.TestPharmacy::class.']');

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exit_code);
        $this->assertStringContainsString('not configured in indexer.model_indexables', $response->output);
    }

    public function test_skip_inactive_models(): void
    {
        $this->createDoctor(['is_active' => true]);
        $this->createDoctor(['is_active' => false]);

        $response = $this->service->run('index:models ['.TestDoctor::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);

        $count = $genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(1, $count);
    }

    public function test_index_with_limit_zero(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createDoctor();
        }

        $response = $this->service->run('index:models _ 00 ['.TestDoctor::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Limit: 0', $response->output);

        $genericIndexer = $this->app->make(GenericIndexerInterface::class);
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);
        $count = $genericIndexer->countIndexed($indexableVO);
        $this->assertEquals(0, $count);
    }

    public function test_index_already_indexed_models(): void
    {
        $this->createDoctor();
        $this->createPharmacy();

        $response1 = $this->service->run('index:models ['.TestDoctor::class.','.TestPharmacy::class.']');
        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);

        $response2 = $this->service->run('index:models ['.TestDoctor::class.','.TestPharmacy::class.']');

        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('already indexed', $response2->output);
        $this->assertStringContainsString('All items were already indexed', $response2->output);
    }
}
