<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Providers;

use AndyDefer\DomainStructures\Normalizers\Core\NormalizerInterface;
use AndyDefer\DomainStructures\Normalizers\NormalizerChain;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Services\Composants\IndexDeleter;
use AndyDefer\LaravelIndexer\Services\Composants\IndexSearcher;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\PhpServices\Configs\TextNormalizerConfig;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerConfigInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\PhpServices\Services\NGramGeneratorService;
use AndyDefer\PhpServices\Services\TextNormalizerService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

final class IndexerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/indexer.php',
            'indexer'
        );

        // ============================================================
        // CONFIGS
        // ============================================================

        $this->app->singleton(IndexerConfig::class, function ($app) {
            return new IndexerConfig($app->make(ConfigRepository::class));
        });

        $this->app->singleton(TextNormalizerConfigInterface::class, function ($app) {
            return new TextNormalizerConfig($app->make(ConfigRepository::class));
        });

        // ============================================================
        // NORMALIZER
        // ============================================================

        $this->app->singleton(NormalizerInterface::class, function () {
            return NormalizerChain::get();
        });

        // ============================================================
        // TEXT NORMALIZER
        // ============================================================

        $this->app->singleton(TextNormalizerInterface::class, function ($app) {
            return new TextNormalizerService(
                $app->make(TextNormalizerConfigInterface::class)
            );
        });

        // ============================================================
        // NGRAM GENERATOR
        // ============================================================

        $this->app->singleton(NGramGeneratorInterface::class, function ($app) {
            return new NGramGeneratorService(
                $app->make(TextNormalizerConfigInterface::class)
            );
        });

        // ============================================================
        // REPOSITORIES
        // ============================================================

        $this->app->singleton(IndexedDocumentRepositoryInterface::class, function () {
            return new IndexedDocumentRepository;
        });

        $this->app->singleton(IndexedTokenRepositoryInterface::class, function () {
            return new IndexedTokenRepository;
        });

        $this->app->singleton(IndexedDocumentRepository::class, function ($app) {
            return $app->make(IndexedDocumentRepositoryInterface::class);
        });

        $this->app->singleton(IndexedTokenRepository::class, function ($app) {
            return $app->make(IndexedTokenRepositoryInterface::class);
        });

        // ============================================================
        // COMPOSANTS - AVEC PARAMÈTRES NOMMÉS
        // ============================================================

        $this->app->singleton(IndexWriter::class, function ($app) {
            return new IndexWriter(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
                textNormalizer: $app->make(TextNormalizerInterface::class),
                ngramGenerator: $app->make(NGramGeneratorInterface::class),
                config: $app->make(IndexerConfig::class),
            );
        });

        $this->app->singleton(IndexDeleter::class, function ($app) {
            return new IndexDeleter(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
            );
        });

        $this->app->singleton(IndexSearcher::class, function ($app) {
            return new IndexSearcher(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
                textNormalizer: $app->make(TextNormalizerInterface::class),
                config: $app->make(IndexerConfig::class),
            );
        });

        // ============================================================
        // SERVICES
        // ============================================================

        $this->app->singleton(IndexerInterface::class, function ($app) {
            return new IndexerService(
                writer: $app->make(IndexWriter::class),
                deleter: $app->make(IndexDeleter::class),
                searcher: $app->make(IndexSearcher::class),
            );
        });

        $this->app->singleton(IndexerService::class, function ($app) {
            return $app->make(IndexerInterface::class);
        });

        // ============================================================
        // ALIAS
        // ============================================================

        $this->app->alias(IndexedDocumentRepositoryInterface::class, 'indexer.document.repository');
        $this->app->alias(IndexedTokenRepositoryInterface::class, 'indexer.token.repository');
        $this->app->alias(IndexerInterface::class, 'indexer.service');
        $this->app->alias(IndexWriter::class, 'indexer.writer');
        $this->app->alias(IndexDeleter::class, 'indexer.deleter');
        $this->app->alias(IndexSearcher::class, 'indexer.searcher');
    }

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
}
