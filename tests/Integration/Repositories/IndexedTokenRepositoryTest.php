<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Repositories;

use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Records\IndexedTokenFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Support\Collection;

final class IndexedTokenRepositoryTest extends IntegrationTestCase
{
    private IndexedTokenRepository $repository;

    private IndexedDocumentRepository $documentRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new IndexedTokenRepository;
        $this->documentRepository = new IndexedDocumentRepository;
    }

    private function createDocument(string $fingerprint, string $cluster): IndexedDocument
    {
        $record = IndexedDocumentRecord::from([
            'fingerprint' => $fingerprint,
            'cluster' => $cluster,
            'data' => [
                'name' => 'Test',
            ],
        ]);

        return $this->documentRepository->create($record);
    }

    private function createToken(
        string $documentId,
        string $token,
        string $field,
        string $originalText,
        GramType $type = GramType::LEXICAL
    ): IndexedToken {
        $record = new IndexedTokenRecord(
            document_id: $documentId,
            token_type: $type,
            token: $token,
            field: $field,
            original_text: $originalText,
        );

        return $this->repository->create($record);
    }

    // ==================== TESTS CREATE ====================

    public function test_create_persists_token(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $record = new IndexedTokenRecord(
            document_id: $doc->id,
            token_type: GramType::LEXICAL,
            token: 'john',
            field: 'name',
            original_text: 'John',
        );

        $token = $this->repository->create($record);

        $this->assertInstanceOf(IndexedToken::class, $token);
        $this->assertNotNull($token->id);
        $this->assertEquals($doc->id, $token->document_id);
        $this->assertEquals('lexical', $token->token_type->value);
        $this->assertEquals('john', $token->token);
        $this->assertEquals('name', $token->field);
        $this->assertEquals('John', $token->original_text);

        $found = $this->repository->find($token->id);
        $this->assertNotNull($found);
        $this->assertInstanceOf(IndexedToken::class, $found);
        $this->assertEquals('john', $found->token);
        $this->assertEquals('John', $found->original_text);
    }

    // ==================== TESTS FIND ====================

    public function test_find_returns_token(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $created = $this->createToken($doc->id, 'john', 'name', 'John');

        $found = $this->repository->find($created->id);

        $this->assertNotNull($found);
        $this->assertInstanceOf(IndexedToken::class, $found);
        $this->assertEquals($created->id, $found->id);
        $this->assertEquals('john', $found->token);
        $this->assertEquals('John', $found->original_text);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $found = $this->repository->find('non-existent-id');
        $this->assertNull($found);
    }

    // ==================== TESTS FIND BY TOKEN ====================

    public function test_find_by_token_returns_collection(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $results = $this->repository->findByToken('john');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertEquals('john', $token->token);
        }
    }

    // ==================== TESTS FIND BY TYPE ====================

    public function test_find_by_type_returns_collection(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $this->createToken($doc->id, 'john', 'name', 'John', GramType::LEXICAL);
        $this->createToken($doc->id, 'JN', 'name', 'John', GramType::METAPHONE);
        $this->createToken($doc->id, 'jane', 'name', 'Jane', GramType::LEXICAL);

        $results = $this->repository->findByType(GramType::LEXICAL);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertEquals('lexical', $token->token_type->value);
        }
    }

    // ==================== TESTS FIND BY FIELD ====================

    public function test_find_by_field_returns_collection(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');
        $this->createToken($doc->id, 'john', 'email', 'John');

        $results = $this->repository->findByField('name');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertEquals('name', $token->field);
        }
    }

    // ==================== TESTS FIND BY DOCUMENT ====================

    public function test_find_by_document_id_returns_collection(): void
    {
        $doc1 = $this->createDocument('App.Models.User|123', 'model:User');
        $doc2 = $this->createDocument('App.Models.User|456', 'model:User');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'doe', 'name', 'Doe');
        $this->createToken($doc2->id, 'jane', 'name', 'Jane');

        $results = $this->repository->findByDocumentId($doc1->id);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertEquals($doc1->id, $token->document_id);
        }
    }

    // ==================== TESTS FIND BY NAMESPACE ====================

    public function test_find_by_namespace_returns_collection(): void
    {
        $doc1 = $this->createDocument('App.Models.User|123', 'model:User');
        $doc2 = $this->createDocument('App.Models.Product|456', 'model:Product');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'doe', 'name', 'Doe');
        $this->createToken($doc2->id, 'laptop', 'name', 'Laptop');

        $results = $this->repository->findByNamespace('App.Models.User');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
        }
    }

    // ==================== TESTS AUTOCOMPLETE ====================

    public function test_autocomplete_returns_distinct_tokens(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $results = $this->repository->autocomplete('jo', 10);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals('john', $results->first()->token);
    }

    // ==================== TESTS GET DOCUMENT IDS ====================

    public function test_get_document_ids_for_token(): void
    {
        $doc1 = $this->createDocument('App.Models.User|123', 'model:User');
        $doc2 = $this->createDocument('App.Models.User|456', 'model:User');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'john', 'email', 'John');
        $this->createToken($doc2->id, 'john', 'name', 'John');

        $results = $this->repository->getDocumentIdsForToken('john');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
        $this->assertContains($doc1->id, $results->toArray());
        $this->assertContains($doc2->id, $results->toArray());
    }

    // ==================== TESTS COUNTS ====================

    public function test_count_distinct_tokens(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $count = $this->repository->countDistinctTokens();
        $this->assertEquals(2, $count);
    }

    // ==================== TESTS DELETE ====================

    public function test_delete_by_document_id(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');

        $count = $this->repository->deleteByDocumentId($doc->id);
        $this->assertEquals(2, $count);

        $tokens = $this->repository->findByDocumentId($doc->id);
        $this->assertCount(0, $tokens);
    }

    // ==================== TESTS FIND BY WITH FILTERS ====================

    public function test_find_by_with_token_filter(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');

        $filters = new IndexedTokenFiltersRecord(
            token: 'john'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: 10,
            sortBy: new SortColumns('token:asc')
        );

        $results = $this->repository->findBy($findBy);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertEquals('john', $token->token);
        }
    }

    // ==================== TESTS FREQUENCY ====================

    public function test_increment_frequency(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');
        $token = $this->createToken($doc->id, 'john', 'name', 'John');

        $this->assertEquals(1, $token->frequency);

        $this->repository->incrementFrequency($token->id);

        $found = $this->repository->find($token->id);
        $this->assertEquals(2, $found->frequency);

        $this->repository->incrementFrequency($token->id);

        $found = $this->repository->find($token->id);
        $this->assertEquals(3, $found->frequency);
    }

    public function test_find_by_token_field_and_document(): void
    {
        $doc = $this->createDocument('App.Models.User|123', 'model:User');

        $this->createToken($doc->id, 'john', 'name', 'John', GramType::LEXICAL);
        $this->createToken($doc->id, 'john', 'email', 'John', GramType::LEXICAL);
        $this->createToken($doc->id, 'JN', 'name', 'John', GramType::METAPHONE);

        $found = $this->repository->findByTokenFieldAndDocument(
            'john',
            'name',
            $doc->id,
            GramType::LEXICAL
        );

        $this->assertNotNull($found);
        $this->assertEquals('john', $found->token);
        $this->assertEquals('name', $found->field);
        $this->assertEquals($doc->id, $found->document_id);
        $this->assertEquals(GramType::LEXICAL, $found->token_type);

        $notFound = $this->repository->findByTokenFieldAndDocument(
            'john',
            'email',
            $doc->id,
            GramType::LEXICAL
        );

        $this->assertNotNull($notFound);
        $this->assertEquals('email', $notFound->field);

        $null = $this->repository->findByTokenFieldAndDocument(
            'john',
            'name',
            $doc->id,
            GramType::METAPHONE
        );

        $this->assertNull($null);
    }
}
