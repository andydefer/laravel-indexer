<?php

// tests/Integration/Services/Visitors/IndexableFieldDiscoveryVisitorTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services\Visitors;

use AndyDefer\LaravelIndexer\Services\Visitors\IndexableFieldDiscoveryVisitor;
use AndyDefer\LaravelIndexer\Tests\Fixtures\CodeSnippets\IndexableFieldSnippets;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class IndexableFieldDiscoveryVisitorTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public function test_visitor_extracts_simple_fields(): void
    {
        $content = IndexableFieldSnippets::SIMPLE_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('slug', $fields);
        $this->assertCount(3, $fields);
    }

    public function test_visitor_extracts_nested_fields(): void
    {
        $content = IndexableFieldSnippets::NESTED_FIELDS;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('profile', $fields);
        $this->assertContains('profile.bio', $fields);
        $this->assertContains('profile.social', $fields);
        $this->assertContains('profile.social.twitter', $fields);
        $this->assertContains('profile.social.github', $fields);
        $this->assertCount(7, $fields);
    }

    public function test_visitor_extracts_deep_nested_fields(): void
    {
        $content = IndexableFieldSnippets::DEEP_NESTED_FIELDS;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('profile.personal.bio', $fields);
        $this->assertContains('profile.personal.social.twitter', $fields);
        $this->assertContains('profile.personal.social.github', $fields);
        $this->assertContains('profile.personal.social.linkedin.url', $fields);
        $this->assertContains('profile.personal.social.linkedin.handle', $fields);
        $this->assertContains('profile.professional.title', $fields);
        $this->assertContains('profile.professional.company', $fields);
        $this->assertContains('profile.professional.experience.years', $fields);
        $this->assertContains('profile.professional.experience.seniority', $fields);
        $this->assertCount(17, $fields);
    }

    public function test_visitor_extracts_fields_with_variables(): void
    {
        $content = IndexableFieldSnippets::WITH_VARIABLE;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('generic_name', $fields);
        $this->assertContains('slug', $fields);
        $this->assertContains('description', $fields);
        $this->assertCount(4, $fields);
    }

    public function test_visitor_returns_empty_for_non_indexable_class(): void
    {
        $content = IndexableFieldSnippets::WITHOUT_GET_INDEXABLE_DATA;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertEmpty($fields);
    }

    public function test_visitor_returns_empty_for_get_indexable_data_without_return(): void
    {
        $content = IndexableFieldSnippets::WITHOUT_RETURN;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertEmpty($fields);
    }

    public function test_visitor_extracts_fields_with_comments(): void
    {
        $content = IndexableFieldSnippets::WITH_COMMENTS;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('slug', $fields);
        $this->assertContains('description', $fields);
        $this->assertContains('type', $fields);
        $this->assertCount(4, $fields);
    }

    public function test_visitor_extracts_fields_from_pharmacy_model(): void
    {
        $content = IndexableFieldSnippets::PHARMACY_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('slug', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('description', $fields);
        $this->assertContains('website', $fields);
        $this->assertContains('phone_number', $fields);
        $this->assertCount(6, $fields);
    }

    public function test_visitor_extracts_fields_from_specialty_model(): void
    {
        $content = IndexableFieldSnippets::SPECIALTY_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('slug', $fields);
        $this->assertContains('description', $fields);
        $this->assertContains('short_description', $fields);
        $this->assertCount(4, $fields);
    }

    public function test_visitor_returns_fully_qualified_class_name(): void
    {
        $content = IndexableFieldSnippets::SIMPLE_MODEL;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fqcn = $visitor->getFullyQualifiedClassName();

        $this->assertEquals('App\\Models\\User', $fqcn);
    }

    public function test_visitor_handles_multiple_models_in_same_file(): void
    {
        $content = IndexableFieldSnippets::MULTIPLE_MODELS;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fields = $visitor->getFields();

        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('price', $fields);
        $this->assertCount(3, $fields);
    }

    public function test_visitor_handles_no_namespace(): void
    {
        $content = IndexableFieldSnippets::NO_NAMESPACE;
        $ast = $this->parser->parse($content);
        $visitor = new IndexableFieldDiscoveryVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);

        $traverser->traverse($ast);
        $fqcn = $visitor->getFullyQualifiedClassName();

        $this->assertNull($fqcn);
        $fields = $visitor->getFields();
        $this->assertNotEmpty($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
    }
}
