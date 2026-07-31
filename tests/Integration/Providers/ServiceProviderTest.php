<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Providers;

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
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;

final class ServiceProviderTest extends IntegrationTestCase
{
    // ==================== TESTS INSTANCIATION DES CONFIGS ====================

    public function test_indexer_config_interface_can_be_resolved(): void
    {
        $config = $this->app->make(IndexerConfigInterface::class);

        $this->assertInstanceOf(IndexerConfigInterface::class, $config);
        $this->assertNotNull($config->getNgramMinSize());
        $this->assertNotNull($config->getNgramMaxSize());
    }

    // ==================== TESTS INSTANCIATION DES REPOSITORIES ====================

    public function test_indexed_document_repository_interface_can_be_resolved(): void
    {
        $repository = $this->app->make(IndexedDocumentRepositoryInterface::class);

        $this->assertInstanceOf(IndexedDocumentRepositoryInterface::class, $repository);
        $this->assertNotNull($repository->getModel());
    }

    public function test_indexed_token_repository_interface_can_be_resolved(): void
    {
        $repository = $this->app->make(IndexedTokenRepositoryInterface::class);

        $this->assertInstanceOf(IndexedTokenRepositoryInterface::class, $repository);
        $this->assertNotNull($repository->getModel());
    }

    // ==================== TESTS INSTANCIATION DES COMPOSANTS ====================

    public function test_index_writer_can_be_resolved(): void
    {
        $writer = $this->app->make(IndexWriter::class);

        $this->assertInstanceOf(IndexWriter::class, $writer);
    }

    public function test_index_deleter_can_be_resolved(): void
    {
        $deleter = $this->app->make(IndexDeleter::class);

        $this->assertInstanceOf(IndexDeleter::class, $deleter);
    }

    public function test_index_searcher_can_be_resolved(): void
    {
        $searcher = $this->app->make(IndexSearcher::class);

        $this->assertInstanceOf(IndexSearcher::class, $searcher);
    }

    // ==================== TESTS INSTANCIATION DES SERVICES ====================

    public function test_indexer_interface_can_be_resolved(): void
    {
        $indexer = $this->app->make(IndexerInterface::class);

        $this->assertInstanceOf(IndexerInterface::class, $indexer);
        $this->assertInstanceOf(IndexerService::class, $indexer);
    }

    public function test_indexer_service_can_be_resolved(): void
    {
        $service = $this->app->make(IndexerService::class);

        $this->assertInstanceOf(IndexerService::class, $service);
        $this->assertInstanceOf(IndexerInterface::class, $service);
    }

    public function test_generic_indexer_interface_can_be_resolved(): void
    {
        $genericIndexer = $this->app->make(GenericIndexerInterface::class);

        $this->assertInstanceOf(GenericIndexerInterface::class, $genericIndexer);
        $this->assertInstanceOf(GenericIndexerService::class, $genericIndexer);
    }

    public function test_generic_indexer_service_can_be_resolved(): void
    {
        $service = $this->app->make(GenericIndexerService::class);

        $this->assertInstanceOf(GenericIndexerService::class, $service);
        $this->assertInstanceOf(GenericIndexerInterface::class, $service);
    }

    // ==================== TESTS INSTANCIATION DES SERVICES PHP ====================

    public function test_text_normalizer_interface_can_be_resolved(): void
    {
        $normalizer = $this->app->make(TextNormalizerInterface::class);

        $this->assertNotNull($normalizer);
    }

    public function test_ngram_generator_interface_can_be_resolved(): void
    {
        $generator = $this->app->make(NGramGeneratorInterface::class);

        $this->assertNotNull($generator);
    }

    // ==================== TESTS INSTANCIATION DES ALIAS ====================

    public function test_alias_indexer_document_repository_can_be_resolved(): void
    {
        $repository = $this->app->make('indexer.document.repository');

        $this->assertInstanceOf(IndexedDocumentRepositoryInterface::class, $repository);
    }

    public function test_alias_indexer_token_repository_can_be_resolved(): void
    {
        $repository = $this->app->make('indexer.token.repository');

        $this->assertInstanceOf(IndexedTokenRepositoryInterface::class, $repository);
    }

    public function test_alias_indexer_service_can_be_resolved(): void
    {
        $service = $this->app->make('indexer.service');

        $this->assertInstanceOf(IndexerInterface::class, $service);
    }

    public function test_alias_indexer_writer_can_be_resolved(): void
    {
        $writer = $this->app->make('indexer.writer');

        $this->assertInstanceOf(IndexWriter::class, $writer);
    }

    public function test_alias_indexer_deleter_can_be_resolved(): void
    {
        $deleter = $this->app->make('indexer.deleter');

        $this->assertInstanceOf(IndexDeleter::class, $deleter);
    }

    public function test_alias_indexer_searcher_can_be_resolved(): void
    {
        $searcher = $this->app->make('indexer.searcher');

        $this->assertInstanceOf(IndexSearcher::class, $searcher);
    }

    public function test_alias_indexer_generic_can_be_resolved(): void
    {
        $generic = $this->app->make('indexer.generic');

        $this->assertInstanceOf(GenericIndexerInterface::class, $generic);
    }

    public function test_alias_indexer_generic_service_can_be_resolved(): void
    {
        $service = $this->app->make('indexer.generic.service');

        $this->assertInstanceOf(GenericIndexerService::class, $service);
    }

    public function test_alias_indexer_config_can_be_resolved(): void
    {
        $config = $this->app->make('indexer.config');

        $this->assertInstanceOf(IndexerConfigInterface::class, $config);
    }

    // ==================== TESTS DE DÉPENDANCES ====================

    public function test_index_writer_has_all_dependencies(): void
    {
        $writer = $this->app->make(IndexWriter::class);

        $reflection = new \ReflectionClass($writer);
        $properties = $reflection->getProperties();

        $propertyNames = array_map(fn ($p) => $p->getName(), $properties);

        $this->assertContains('documentRepository', $propertyNames);
        $this->assertContains('tokenRepository', $propertyNames);
        $this->assertContains('textNormalizer', $propertyNames);
        $this->assertContains('ngramGenerator', $propertyNames);
        $this->assertContains('config', $propertyNames);
    }

    public function test_index_searcher_has_all_dependencies(): void
    {
        $searcher = $this->app->make(IndexSearcher::class);

        $reflection = new \ReflectionClass($searcher);
        $properties = $reflection->getProperties();

        $propertyNames = array_map(fn ($p) => $p->getName(), $properties);

        $this->assertContains('documentRepository', $propertyNames);
        $this->assertContains('tokenRepository', $propertyNames);
        $this->assertContains('textNormalizer', $propertyNames);
        $this->assertContains('config', $propertyNames);
        $this->assertContains('clusterService', $propertyNames);
    }

    public function test_generic_indexer_service_has_all_dependencies(): void
    {
        $service = $this->app->make(GenericIndexerInterface::class);

        $reflection = new \ReflectionClass($service);
        $properties = $reflection->getProperties();

        $propertyNames = array_map(fn ($p) => $p->getName(), $properties);

        $this->assertContains('indexer', $propertyNames);
        $this->assertContains('documentRepository', $propertyNames);
        $this->assertContains('config', $propertyNames);
    }

    // ==================== TESTS DE NON-ÉCHEC ====================

    public function test_all_services_can_be_resolved_without_error(): void
    {
        $services = [
            IndexerConfigInterface::class,
            IndexedDocumentRepositoryInterface::class,
            IndexedTokenRepositoryInterface::class,
            IndexWriter::class,
            IndexDeleter::class,
            IndexSearcher::class,
            IndexerInterface::class,
            IndexerService::class,
            GenericIndexerInterface::class,
            GenericIndexerService::class,
        ];

        foreach ($services as $service) {
            $this->app->make($service);
            $this->assertTrue(true);
        }
    }

    public function test_all_aliases_can_be_resolved_without_error(): void
    {
        $aliases = [
            'indexer.document.repository',
            'indexer.token.repository',
            'indexer.service',
            'indexer.writer',
            'indexer.deleter',
            'indexer.searcher',
            'indexer.generic',
            'indexer.generic.service',
            'indexer.config',
        ];

        foreach ($aliases as $alias) {
            $this->app->make($alias);
            $this->assertTrue(true);
        }
    }
}
