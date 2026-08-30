<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests;

use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\JsonlCache\JsonlCacheServiceProvider;
use AndyDefer\LaravelCluster\Providers\ClusterServiceProvider;
use AndyDefer\LaravelIndexer\Providers\IndexerServiceProvider;
use AndyDefer\Logger\LoggerServiceProvider;
use AndyDefer\Task\TaskServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    use RefreshDatabase;

    protected string $databasePath;

    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]+m/', '', $text);
    }

    protected function getPackageProviders($app): array
    {
        return [
            JsonlCacheServiceProvider::class,
            ClusterServiceProvider::class,
            LoggerServiceProvider::class,
            DirectiveServiceProvider::class,
            TaskServiceProvider::class,
            IndexerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Définit l'environnement de test avec MySQL par défaut.
     */
    /*  protected function defineEnvironment($app): void
     {
         // Connexion MySQL par défaut
         $app['config']->set('database.default', 'mysql');
         $app['config']->set('database.connections.mysql', [
             'driver' => 'mysql',
             'host' => env('DB_HOST', '127.0.0.1'),
             'port' => env('DB_PORT', '3306'),
             'database' => env('DB_DATABASE', 'indexer_test'),
             'username' => env('DB_USERNAME', 'root'),
             'password' => env('DB_PASSWORD', ''),
             'charset' => 'utf8mb4',
             'collation' => 'utf8mb4_unicode_ci',
             'prefix' => '',
             'strict' => true,
             'engine' => null,
         ]);
     }
 */
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    protected function runMigrations(): void
    {
        $migrationPath = __DIR__.'/Fixtures/migrations';
        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
        $packageMigrations = __DIR__.'/../database/migrations';
        if (is_dir($packageMigrations)) {
            $this->loadMigrationsFrom($packageMigrations);
        }
    }
}
