<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Benchmark\TestCase;

class MysqlBenchmarkTestCase extends BenchmarkTestCase
{
    protected function getDatabaseConfig(): array
    {
        return [
            'driver' => 'mysql',
            'host' => env('BENCHMARK_DB_HOST', '127.0.0.1'),
            'port' => env('BENCHMARK_DB_PORT', '3306'),
            'database' => env('BENCHMARK_DB_DATABASE', 'indexer_benchmark'),
            'username' => env('BENCHMARK_DB_USERNAME', 'root'),
            'password' => env('BENCHMARK_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ];
    }
}
