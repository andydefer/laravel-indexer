<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Benchmark\TestCase;

use Illuminate\Filesystem\Filesystem;

class SqliteBenchmarkTestCase extends BenchmarkTestCase
{
    private string $databasePath;

    protected function getDatabaseConfig(): array
    {
        $fs = new Filesystem;
        $databaseDir = __DIR__.'/../../temp/sqlite_benchmark';

        if (! $fs->isDirectory($databaseDir)) {
            $fs->makeDirectory($databaseDir, 0755, true);
        }

        $this->databasePath = $databaseDir.'/benchmark.sqlite';

        if (! $fs->exists($this->databasePath)) {
            $fs->put($this->databasePath, '');
            $fs->chmod($this->databasePath, 0666);
        }

        return [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $fs = new Filesystem;
        if ($fs->exists($this->databasePath)) {
            $fs->delete($this->databasePath);
        }

        $dir = dirname($this->databasePath);
        if ($fs->isDirectory($dir)) {
            $fs->deleteDirectory($dir);
        }
    }
}
