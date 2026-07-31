<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services;

use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\Repositories\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\Repositories\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexDeleter;
use AndyDefer\LaravelIndexer\Services\Composants\IndexSearcher;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Services\GenericIndexerService;
use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class GenericIndexerServiceTest extends IntegrationTestCase
{
    private GenericIndexerInterface $genericIndexer;

    private static int $doctorCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('indexer.token_types.ngrams.min_size', 2);
        $this->app['config']->set('indexer.token_types.ngrams.max_size', 4);

        // Re-bind IndexerConfig
        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        // Re-bind IndexWriter avec la nouvelle config
        $this->app->singleton(IndexWriter::class, function ($app) {
            return new IndexWriter(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
                textNormalizer: $app->make(TextNormalizerInterface::class),
                ngramGenerator: $app->make(NGramGeneratorInterface::class),
                config: $app->make(IndexerConfigInterface::class),
            );
        });

        // Re-bind IndexSearcher avec la nouvelle config
        $this->app->singleton(IndexSearcher::class, function ($app) {
            return new IndexSearcher(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
                textNormalizer: $app->make(TextNormalizerInterface::class),
                config: $app->make(IndexerConfigInterface::class),
                clusterService: $app->make(ClusterService::class),

            );
        });

        // Re-bind IndexDeleter
        $this->app->singleton(IndexDeleter::class, function ($app) {
            return new IndexDeleter(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
            );
        });

        // Re-bind IndexerService avec les composants rebindés
        $this->app->singleton(IndexerInterface::class, function ($app) {
            return new IndexerService(
                writer: $app->make(IndexWriter::class),
                deleter: $app->make(IndexDeleter::class),
                searcher: $app->make(IndexSearcher::class),
            );
        });

        // Re-bind GenericIndexerService avec la nouvelle config
        $this->app->singleton(GenericIndexerInterface::class, function ($app) {
            return new GenericIndexerService(
                indexer: $app->make(IndexerInterface::class),
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                config: $app->make(IndexerConfigInterface::class),
            );
        });

        $this->genericIndexer = $this->app->make(GenericIndexerInterface::class);
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

    public function test_index_single_document(): void
    {
        $doctor = $this->createDoctor();

        $this->genericIndexer->index($doctor);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);

        $this->assertEquals(1, $count);
        $this->assertTrue($this->genericIndexer->exists($doctor));
    }

    public function test_index_by_id(): void
    {
        $doctor = $this->createDoctor();

        $this->genericIndexer->indexById(TestDoctor::class, $doctor->id);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);

        $this->assertEquals(1, $count);
        $this->assertTrue($this->genericIndexer->existsById(TestDoctor::class, $doctor->id));
    }

    public function test_index_skips_when_not_should_be_indexed(): void
    {
        $doctor = $this->createDoctor(['is_active' => false]);

        $this->genericIndexer->index($doctor);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);

        $this->assertEquals(0, $count);
        $this->assertFalse($this->genericIndexer->exists($doctor));
    }

    public function test_index_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model with ID 99999 not found');

        $this->genericIndexer->indexById(TestDoctor::class, 99999);
    }

    public function test_index_all_documents(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $this->createDoctor(['is_active' => false]);

        $this->genericIndexer->indexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);

        $this->assertEquals(2, $count);
    }

    public function test_index_all_with_empty_dataset(): void
    {
        $this->genericIndexer->indexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);

        $this->assertEquals(0, $count);
    }

    public function test_delete_single_document(): void
    {
        $doctor = $this->createDoctor();

        $this->genericIndexer->index($doctor);
        $this->assertEquals(1, $this->genericIndexer->countIndexed(TestDoctor::class));

        $this->genericIndexer->delete($doctor);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(0, $count);
        $this->assertFalse($this->genericIndexer->exists($doctor));
    }

    public function test_delete_by_id(): void
    {
        $doctor = $this->createDoctor();

        $this->genericIndexer->index($doctor);
        $this->assertEquals(1, $this->genericIndexer->countIndexed(TestDoctor::class));

        $this->genericIndexer->deleteById(TestDoctor::class, $doctor->id);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(0, $count);
        $this->assertFalse($this->genericIndexer->existsById(TestDoctor::class, $doctor->id));
    }

    public function test_delete_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model with ID 99999 not found');

        $this->genericIndexer->deleteById(TestDoctor::class, 99999);
    }

    public function test_delete_all_documents(): void
    {
        $this->createDoctor();
        $this->createDoctor();

        $this->genericIndexer->indexAll(TestDoctor::class);
        $this->assertEquals(2, $this->genericIndexer->countIndexed(TestDoctor::class));

        $this->genericIndexer->deleteAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(0, $count);
    }

    public function test_refresh_document(): void
    {
        $doctor = $this->createDoctor();

        $this->genericIndexer->index($doctor);
        $this->assertEquals(1, $this->genericIndexer->countIndexed(TestDoctor::class));

        $this->genericIndexer->refresh($doctor);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(1, $count);
        $this->assertTrue($this->genericIndexer->exists($doctor));
    }

    public function test_refresh_by_id(): void
    {
        $doctor = $this->createDoctor();

        $this->genericIndexer->index($doctor);
        $this->assertEquals(1, $this->genericIndexer->countIndexed(TestDoctor::class));

        $this->genericIndexer->refreshById(TestDoctor::class, $doctor->id);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(1, $count);
        $this->assertTrue($this->genericIndexer->existsById(TestDoctor::class, $doctor->id));
    }

    public function test_refresh_skips_when_not_should_be_indexed(): void
    {
        $doctor = $this->createDoctor(['is_active' => true]);

        $this->genericIndexer->index($doctor);
        $this->assertEquals(1, $this->genericIndexer->countIndexed(TestDoctor::class));

        $doctor->is_active = false;
        $doctor->save();

        $this->genericIndexer->refresh($doctor);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(0, $count);
    }

    public function test_refresh_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Model with ID 99999 not found');

        $this->genericIndexer->refreshById(TestDoctor::class, 99999);
    }

    public function test_reindex_all_documents(): void
    {
        $this->createDoctor();
        $this->createDoctor();

        $this->genericIndexer->indexAll(TestDoctor::class);
        $this->assertEquals(2, $this->genericIndexer->countIndexed(TestDoctor::class));

        $this->genericIndexer->reindexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(2, $count);
    }

    public function test_count_indexed(): void
    {
        $this->createDoctor();
        $this->createDoctor();

        $this->genericIndexer->indexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);

        $this->assertEquals(2, $count);
    }

    public function test_exists_returns_true_when_indexed(): void
    {
        $doctor = $this->createDoctor();

        $this->genericIndexer->index($doctor);

        $this->assertTrue($this->genericIndexer->exists($doctor));
        $this->assertTrue($this->genericIndexer->existsById(TestDoctor::class, $doctor->id));
    }

    public function test_exists_returns_false_when_not_indexed(): void
    {
        $doctor = $this->createDoctor(['is_active' => false]);

        $this->assertFalse($this->genericIndexer->exists($doctor));
        $this->assertFalse($this->genericIndexer->existsById(TestDoctor::class, $doctor->id));
    }

    public function test_set_batch_size(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createDoctor();
        }

        $this->genericIndexer->setBatchSize(10);
        $this->genericIndexer->indexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(25, $count);
    }

    public function test_set_limit(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createDoctor();
        }

        $this->genericIndexer->setBatchSize(5);
        $this->genericIndexer->setLimit(10);
        $this->genericIndexer->indexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(10, $count);
    }

    public function test_set_limit_to_null(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createDoctor();
        }

        $this->genericIndexer->setBatchSize(5);
        $this->genericIndexer->setLimit(null);
        $this->genericIndexer->indexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(15, $count);
    }

    public function test_set_limit_with_reindex(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createDoctor();
        }

        $this->genericIndexer->setBatchSize(10);
        $this->genericIndexer->setLimit(5);
        $this->genericIndexer->reindexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(5, $count);
    }

    public function test_index_all_with_limit_and_inactive_models(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createDoctor(['is_active' => true]);
        }

        for ($i = 0; $i < 10; $i++) {
            $this->createDoctor(['is_active' => false]);
        }

        $this->genericIndexer->setBatchSize(5);
        $this->genericIndexer->setLimit(15);
        $this->genericIndexer->indexAll(TestDoctor::class);

        $count = $this->genericIndexer->countIndexed(TestDoctor::class);
        $this->assertEquals(10, $count);
    }
}
