<?php

// src/Services/Visitors/IndexableFieldDiscoveryVisitor.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Visitors;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that extracts field keys from getIndexableData() method.
 *
 * Traverses the abstract syntax tree to find the getIndexableData() method
 * and extracts all array keys from:
 * - StrictAssociative::from([...])
 * - new StrictAssociative([...])
 * - $data = [...]; StrictAssociative::from($data)
 */
final class IndexableFieldDiscoveryVisitor extends NodeVisitorAbstract
{
    private ?string $currentNamespace = null;

    private ?string $currentClass = null;

    /** @var array<string, Array_> */
    private array $variableMap = [];

    /** @var array<string> */
    private array $fields = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Namespace_) {
            $this->currentNamespace = $node->name?->toString();

            return null;
        }

        if ($node instanceof Class_) {
            $this->currentClass = $node->name?->toString();

            return null;
        }

        if ($node instanceof ClassMethod && $node->name->toString() === 'getIndexableData') {
            $this->extractFieldsFromMethod($node);

            return null;
        }

        return null;
    }

    /**
     * Extracts field keys from the getIndexableData method.
     */
    private function extractFieldsFromMethod(ClassMethod $method): void
    {
        $stmts = $method->getStmts();

        if ($stmts === null) {
            return;
        }

        // Première passe : collecter les variables assignées
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Expression && $stmt->expr instanceof Assign) {
                $assign = $stmt->expr;
                $var = $assign->var;

                if ($var instanceof Variable && is_string($var->name)) {
                    $value = $assign->expr;

                    if ($value instanceof Array_) {
                        $this->variableMap[$var->name] = $value;
                    }
                }
            }
        }

        // Deuxième passe : chercher les retours
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_) {
                $expr = $stmt->expr;

                $arrayArg = $this->extractArrayArgument($expr);

                if ($arrayArg !== null) {
                    $this->extractArrayKeys($arrayArg);
                }
            }
        }
    }

    /**
     * Extracts the array argument from various call types.
     *
     * Supports:
     * - StrictAssociative::from([...])
     * - new StrictAssociative([...])
     * - StrictAssociative::from($variable)
     * - $this->from([...])
     */
    private function extractArrayArgument(Node $expr): ?Array_
    {
        // Cas 1: StaticCall - StrictAssociative::from([...])
        if ($expr instanceof StaticCall) {
            $callName = $expr->name->toString();

            $class = $expr->class;
            $isStrictAssociative = false;

            if ($class instanceof Node\Name) {
                $className = $class->toString();
                if (str_ends_with($className, 'StrictAssociative')) {
                    $isStrictAssociative = true;
                }
            }

            if ($isStrictAssociative && $callName === 'from') {
                $args = $expr->getArgs();

                if (! empty($args)) {
                    $arg = $args[0]->value;

                    if ($arg instanceof Array_) {
                        return $arg;
                    }

                    if ($arg instanceof Variable && is_string($arg->name)) {
                        if (isset($this->variableMap[$arg->name])) {
                            return $this->variableMap[$arg->name];
                        }
                    }
                }
            }

            return null;
        }

        // Cas 2: New_ - new StrictAssociative([...])
        if ($expr instanceof New_) {
            $class = $expr->class;
            $isStrictAssociative = false;

            if ($class instanceof Node\Name) {
                $className = $class->toString();
                if (str_ends_with($className, 'StrictAssociative')) {
                    $isStrictAssociative = true;
                }
            }

            if ($isStrictAssociative) {
                $args = $expr->getArgs();

                if (! empty($args)) {
                    $arg = $args[0]->value;

                    if ($arg instanceof Array_) {
                        return $arg;
                    }

                    if ($arg instanceof Variable && is_string($arg->name)) {
                        if (isset($this->variableMap[$arg->name])) {
                            return $this->variableMap[$arg->name];
                        }
                    }
                }
            }

            return null;
        }

        // Cas 3: MethodCall - $this->from([...])
        if ($expr instanceof MethodCall) {
            $callName = $expr->name->toString();

            if ($callName === 'from') {
                $args = $expr->getArgs();

                if (! empty($args)) {
                    $arg = $args[0]->value;

                    if ($arg instanceof Array_) {
                        return $arg;
                    }

                    if ($arg instanceof Variable && is_string($arg->name)) {
                        if (isset($this->variableMap[$arg->name])) {
                            return $this->variableMap[$arg->name];
                        }
                    }
                }
            }

            return null;
        }

        return null;
    }

    /**
     * Extracts keys from an array node.
     */
    private function extractArrayKeys(Array_ $array, string $prefix = ''): void
    {
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->key instanceof Node\Scalar\String_) {
                $key = $item->key->value;
                $fullKey = $prefix !== '' ? $prefix.'.'.$key : $key;

                $this->fields[] = $fullKey;

                if ($item->value instanceof Array_) {
                    $this->extractArrayKeys($item->value, $fullKey);
                }

                if ($item->value instanceof Node\Expr\Ternary) {
                    $then = $item->value->if;
                    $else = $item->value->else;

                    if ($then instanceof Array_) {
                        $this->extractArrayKeys($then, $fullKey);
                    }

                    if ($else instanceof Array_) {
                        $this->extractArrayKeys($else, $fullKey);
                    }
                }
            } else {
                continue;
            }
        }
    }

    /**
     * Returns the list of discovered field keys.
     *
     * @return array<string>
     */
    public function getFields(): array
    {
        return array_unique($this->fields);
    }

    /**
     * Returns the fully qualified class name.
     */
    public function getFullyQualifiedClassName(): ?string
    {
        if ($this->currentNamespace !== null && $this->currentClass !== null) {
            return $this->currentNamespace.'\\'.$this->currentClass;
        }

        return null;
    }
}
