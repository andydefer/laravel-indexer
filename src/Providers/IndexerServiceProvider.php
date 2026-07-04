<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Providers;

use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexDeleter;
use AndyDefer\LaravelIndexer\Services\Composants\IndexSearcher;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Services\IndexerService;
use Illuminate\Support\ServiceProvider;

final class IndexerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/indexer.php',
            'indexer'
        );

        $this->app->singleton(IndexWriter::class);
        $this->app->singleton(IndexDeleter::class);
        $this->app->singleton(IndexSearcher::class);

        $this->app->singleton(IndexerInterface::class, function ($app) {
            return new IndexerService(
                writer: $app->make(IndexWriter::class),
                deleter: $app->make(IndexDeleter::class),
                searcher: $app->make(IndexSearcher::class),
            );
        });
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
