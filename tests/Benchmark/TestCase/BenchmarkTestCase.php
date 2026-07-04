<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Benchmark\TestCase;

use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\JsonlCache\JsonlCacheServiceProvider;
use AndyDefer\LaravelIndexer\Providers\IndexerServiceProvider;
use AndyDefer\Logger\LoggerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class BenchmarkTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            JsonlCacheServiceProvider::class,
            LoggerServiceProvider::class,
            DirectiveServiceProvider::class,
            IndexerServiceProvider::class,
        ];
    }

    abstract protected function getDatabaseConfig(): array;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'benchmark');
        $app['config']->set('database.connections.benchmark', $this->getDatabaseConfig());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function runMigrations(): void
    {
        $migrationPath = __DIR__.'/../../Fixtures/migrations';
        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
        $packageMigrations = __DIR__.'/../../../database/migrations';
        if (is_dir($packageMigrations)) {
            $this->loadMigrationsFrom($packageMigrations);
        }
    }
}
