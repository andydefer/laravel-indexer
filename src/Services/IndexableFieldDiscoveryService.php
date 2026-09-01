<?php

// src/Services/IndexableFieldDiscoveryService.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services;

use AndyDefer\LaravelIndexer\Contracts\Services\IndexableFieldDiscoveryServiceInterface;
use AndyDefer\LaravelIndexer\Services\Visitors\IndexableFieldDiscoveryVisitor;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;

/**
 * Service for discovering indexable fields from Eloquent models using AST analysis.
 *
 * Parses the source code of models to extract field keys from their
 * getIndexableData() method without instantiating the model.
 *
 * Supports:
 * - StrictAssociative::from([...])
 * - new StrictAssociative([...])
 * - $data = [...]; StrictAssociative::from($data)
 * - $this->from([...])
 */
final class IndexableFieldDiscoveryService implements IndexableFieldDiscoveryServiceInterface
{
    public function __construct(
        private readonly Parser $parser,
    ) {}

    public function discoverFields(string $modelClass): array
    {
        try {
            $reflection = new \ReflectionClass($modelClass);
            $filename = $reflection->getFileName();

            if ($filename === false) {
                return [];
            }

            $content = file_get_contents($filename);

            if ($content === false) {
                return [];
            }

            try {
                $ast = $this->parser->parse($content);

                if ($ast === null) {
                    return [];
                }

                $visitor = new IndexableFieldDiscoveryVisitor;
                $traverser = new NodeTraverser;
                $traverser->addVisitor($visitor);
                $traverser->traverse($ast);

                return $visitor->getFields();
            } catch (Error $e) {
                return [];
            }
        } catch (\Throwable $th) {
            return [];
        }
    }

    public function discoverFieldsForMany(array $modelClasses): array
    {
        $result = [];

        foreach ($modelClasses as $modelClass) {
            $result[$modelClass] = $this->discoverFields($modelClass);
        }

        return $result;
    }

    public function discoverFieldsInDirectory(string $directory, string $namespace): array
    {
        $result = [];
        $files = glob($directory.'/*.php');

        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fqcn = $namespace.'\\'.$className;

            if (! class_exists($fqcn)) {
                continue;
            }

            $fields = $this->discoverFields($fqcn);

            if (! empty($fields)) {
                $result[$fqcn] = $fields;
            }
        }

        return $result;
    }
}
