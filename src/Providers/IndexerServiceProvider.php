<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Providers;

use AndyDefer\DomainStructures\Normalizers\Core\NormalizerInterface;
use AndyDefer\DomainStructures\Normalizers\NormalizerChain;
use AndyDefer\LaravelCluster\ClusterQuery;
use AndyDefer\LaravelCluster\Services\ClusterService;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\Repositories\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\Repositories\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Services\Composants\IndexDeleter;
use AndyDefer\LaravelIndexer\Services\Composants\IndexSearcher;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Services\GenericIndexerService;
use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\PhpServices\Configs\TextNormalizerConfig;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerConfigInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\PhpServices\Services\NGramGeneratorService;
use AndyDefer\PhpServices\Services\TextNormalizerService;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel service provider for the indexer package.
 *
 * Registers all services, repositories, and components required by the
 * indexing system in the Laravel service container.
 *
 * Provides both interface-based and alias-based resolution for all
 * major components of the package.
 */
final class IndexerServiceProvider extends ServiceProvider
{
    /**
     * Register the package's services in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/indexer.php',
            'indexer'
        );

        $this->registerConfigs();
        $this->registerNormalizer();
        $this->registerTextNormalizer();
        $this->registerNgramGenerator();
        $this->registerClusterService();
        $this->registerRepositories();
        $this->registerComposants();
        $this->registerServices();
        $this->registerAliases();
    }

    /**
     * Boot the package's services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->publishes([
            __DIR__.'/../../config/indexer.php' => config_path('indexer.php'),
        ], 'indexer-config');

        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'indexer-migrations');
    }

    // ============================================================
    // REGISTRATION METHODS
    // ============================================================

    /**
     * Registers all configuration classes in the container.
     */
    private function registerConfigs(): void
    {
        $this->app->singleton(IndexerConfig::class, function ($app): IndexerConfig {
            return new IndexerConfig($app['config']);
        });

        $this->app->singleton(TextNormalizerConfig::class, function ($app): TextNormalizerConfig {
            return new TextNormalizerConfig($app['config']);
        });

        $this->app->bind(IndexerConfigInterface::class, IndexerConfig::class);
        $this->app->bind(TextNormalizerConfigInterface::class, TextNormalizerConfig::class);

    }

    /**
     * Registers the normalizer service in the container.
     */
    private function registerNormalizer(): void
    {

        $this->app->singleton(NormalizerInterface::class, function ($app): NormalizerInterface {
            return $app->make(NormalizerChain::class);
        });
    }

    /**
     * Registers the text normalizer service in the container.
     */
    private function registerTextNormalizer(): void
    {
        $this->app->singleton(TextNormalizerService::class, function ($app): TextNormalizerService {
            return new TextNormalizerService(
                $app->make(TextNormalizerConfigInterface::class)
            );
        });

        $this->app->singleton(TextNormalizerInterface::class, function ($app): TextNormalizerInterface {
            return $app->make(TextNormalizerService::class);
        });
    }

    /**
     * Registers the n-gram generator service in the container.
     */
    private function registerNgramGenerator(): void
    {
        $this->app->singleton(NGramGeneratorService::class, function ($app): NGramGeneratorService {
            return new NGramGeneratorService(
                $app->make(TextNormalizerConfigInterface::class)
            );
        });

        $this->app->singleton(NGramGeneratorInterface::class, function ($app): NGramGeneratorInterface {
            return $app->make(NGramGeneratorService::class);
        });
    }

    /**
     * Registers the cluster service in the container.
     */
    private function registerClusterService(): void
    {
        $this->app->singleton(ClusterService::class, function ($app): ClusterService {
            return new ClusterService($app->make(ClusterQuery::class));
        });
    }

    /**
     * Registers all repository classes in the container.
     */
    private function registerRepositories(): void
    {
        $this->app->singleton(IndexedDocumentRepository::class, function (): IndexedDocumentRepository {
            return new IndexedDocumentRepository;
        });

        $this->app->singleton(IndexedTokenRepository::class, function (): IndexedTokenRepository {
            return new IndexedTokenRepository;
        });

        $this->app->singleton(IndexedDocumentRepositoryInterface::class, function ($app): IndexedDocumentRepositoryInterface {
            return $app->make(IndexedDocumentRepository::class);
        });

        $this->app->singleton(IndexedTokenRepositoryInterface::class, function ($app): IndexedTokenRepositoryInterface {
            return $app->make(IndexedTokenRepository::class);
        });
    }

    /**
     * Registers the indexer composants (Writer, Deleter, Searcher) in the container.
     */
    private function registerComposants(): void
    {
        $this->app->singleton(IndexWriter::class, function ($app): IndexWriter {
            return new IndexWriter(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
                textNormalizer: $app->make(TextNormalizerInterface::class),
                ngramGenerator: $app->make(NGramGeneratorInterface::class),
                config: $app->make(IndexerConfigInterface::class),
            );
        });

        $this->app->singleton(IndexDeleter::class, function ($app): IndexDeleter {
            return new IndexDeleter(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
            );
        });

        $this->app->singleton(IndexSearcher::class, function ($app): IndexSearcher {
            return new IndexSearcher(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
                textNormalizer: $app->make(TextNormalizerInterface::class),
                config: $app->make(IndexerConfigInterface::class),
                clusterService: $app->make(ClusterService::class),
            );
        });
    }

    /**
     * Registers the main indexer services in the container.
     */
    private function registerServices(): void
    {
        $this->app->singleton(IndexerService::class, function ($app): IndexerService {
            return new IndexerService(
                writer: $app->make(IndexWriter::class),
                deleter: $app->make(IndexDeleter::class),
                searcher: $app->make(IndexSearcher::class),
            );
        });

        $this->app->singleton(IndexerInterface::class, function ($app): IndexerInterface {
            return $app->make(IndexerService::class);
        });

        $this->app->singleton(GenericIndexerService::class, function ($app): GenericIndexerService {
            return new GenericIndexerService(
                indexer: $app->make(IndexerInterface::class),
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                config: $app->make(IndexerConfigInterface::class),
            );
        });

        $this->app->singleton(GenericIndexerInterface::class, function ($app): GenericIndexerInterface {
            return $app->make(GenericIndexerService::class);
        });
    }

    /**
     * Registers container aliases for convenient dependency injection.
     */
    private function registerAliases(): void
    {
        $this->app->alias(IndexedDocumentRepositoryInterface::class, 'indexer.document.repository');
        $this->app->alias(IndexedTokenRepositoryInterface::class, 'indexer.token.repository');
        $this->app->alias(IndexerInterface::class, 'indexer.service');
        $this->app->alias(IndexWriter::class, 'indexer.writer');
        $this->app->alias(IndexDeleter::class, 'indexer.deleter');
        $this->app->alias(IndexSearcher::class, 'indexer.searcher');
        $this->app->alias(GenericIndexerInterface::class, 'indexer.generic');
        $this->app->alias(GenericIndexerService::class, 'indexer.generic.service');
        $this->app->alias(IndexerConfigInterface::class, 'indexer.config');
    }
}
