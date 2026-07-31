<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    // ============================================================================
    // HELPERS
    // ============================================================================

    private function createDocument(string $fingerprint, array $cluster = ['model' => 'User']): IndexedDocument
    {
        return IndexedDocument::create([
            'id' => (string) Str::uuid(),
            'fingerprint' => $fingerprint,
            'cluster' => $cluster,
            'data' => ['name' => 'Test'],
        ]);
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

    // ============================================================================
    // TESTS - Create
    // ============================================================================

    public function test_create_persists_token(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');

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
        $this->assertSame($doc->id, $token->document_id);
        $this->assertSame('lexical', $token->token_type->value);
        $this->assertSame('john', $token->token);
        $this->assertSame('name', $token->field);
        $this->assertSame('John', $token->original_text);

        $found = $this->repository->find($token->id);
        $this->assertNotNull($found);
        $this->assertSame('john', $found->token);
        $this->assertSame('John', $found->original_text);
    }

    // ============================================================================
    // TESTS - Find Methods
    // ============================================================================

    public function test_find_returns_token(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $created = $this->createToken($doc->id, 'john', 'name', 'John');

        $found = $this->repository->find($created->id);

        $this->assertNotNull($found);
        $this->assertInstanceOf(IndexedToken::class, $found);
        $this->assertSame($created->id, $found->id);
        $this->assertSame('john', $found->token);
        $this->assertSame('John', $found->original_text);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $found = $this->repository->find('non-existent-id');
        $this->assertNull($found);
    }

    public function test_find_by_token_returns_collection(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $results = $this->repository->findByToken('john');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertSame('john', $token->token);
        }
    }

    public function test_find_by_type_returns_collection(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John', GramType::LEXICAL);
        $this->createToken($doc->id, 'JN', 'name', 'John', GramType::METAPHONE);
        $this->createToken($doc->id, 'jane', 'name', 'Jane', GramType::LEXICAL);

        $results = $this->repository->findByType(GramType::LEXICAL);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertSame('lexical', $token->token_type->value);
        }
    }

    public function test_find_by_field_returns_collection(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');
        $this->createToken($doc->id, 'john', 'email', 'John');

        $results = $this->repository->findByField('name');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertSame('name', $token->field);
        }
    }

    public function test_find_by_document_id_returns_collection(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123');
        $doc2 = $this->createDocument('App\\Models\\User|456');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'doe', 'name', 'Doe');
        $this->createToken($doc2->id, 'jane', 'name', 'Jane');

        $results = $this->repository->findByDocumentId($doc1->id);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertSame($doc1->id, $token->document_id);
        }
    }

    public function test_find_by_namespace_returns_collection(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123');
        $doc2 = $this->createDocument('App\\Models\\Product|456');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'doe', 'name', 'Doe');
        $this->createToken($doc2->id, 'laptop', 'name', 'Laptop');

        $results = $this->repository->findByNamespace('App\\Models\\User');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
        }
    }

    public function test_find_by_cluster_query_returns_filtered_tokens(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123', ['status' => 'active', 'role' => 'admin']);
        $doc2 = $this->createDocument('App\\Models\\User|456', ['status' => 'inactive', 'role' => 'doctor']);

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'jane', 'name', 'Jane');

        $results = $this->repository->findByClusterQuery('status=active & role=admin');

        $this->assertCount(1, $results);
        $this->assertSame('john', $results->first()->token);
    }

    public function test_find_by_token_and_cluster_query(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123', ['status' => 'active', 'role' => 'admin']);
        $doc2 = $this->createDocument('App\\Models\\User|456', ['status' => 'active', 'role' => 'doctor']);

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'john', 'name', 'John');

        $results = $this->repository->findByTokenAndClusterQuery(
            'john',
            'status=active & role=admin'
        );

        $this->assertCount(1, $results);
        $this->assertSame('john', $results->first()->token);
        $this->assertSame('admin', $results->first()->document->cluster->get('role'));
    }

    // ============================================================================
    // TESTS - Autocomplete
    // ============================================================================

    public function test_autocomplete_returns_distinct_tokens(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $results = $this->repository->autocomplete('jo', 10);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertSame('john', $results->first()->token);
    }

    public function test_autocomplete_respects_limit(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');
        $this->createToken($doc->id, 'jack', 'name', 'Jack');

        $results = $this->repository->autocomplete('ja', 2);

        $this->assertCount(2, $results);
    }

    // ============================================================================
    // TESTS - Starting With
    // ============================================================================

    public function test_starting_with_returns_tokens(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');
        $this->createToken($doc->id, 'bob', 'name', 'Bob');

        $results = $this->repository->startingWith('j');

        $this->assertCount(2, $results);
        $this->assertContains('john', $results->pluck('token')->toArray());
        $this->assertContains('jane', $results->pluck('token')->toArray());
        $this->assertNotContains('bob', $results->pluck('token')->toArray());
    }

    public function test_starting_with_respects_limit(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');
        $this->createToken($doc->id, 'jack', 'name', 'Jack');

        $results = $this->repository->startingWith('j', 2);

        $this->assertCount(2, $results);
    }

    // ============================================================================
    // TESTS - Get Document IDs
    // ============================================================================

    public function test_get_document_ids_for_token(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123');
        $doc2 = $this->createDocument('App\\Models\\User|456');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'john', 'email', 'John');
        $this->createToken($doc2->id, 'john', 'name', 'John');

        $results = $this->repository->getDocumentIdsForToken('john');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
        $this->assertContains($doc1->id, $results->toArray());
        $this->assertContains($doc2->id, $results->toArray());
    }

    public function test_get_document_ids_for_token_and_field(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123');
        $doc2 = $this->createDocument('App\\Models\\User|456');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'john', 'email', 'John');
        $this->createToken($doc2->id, 'john', 'name', 'John');

        $results = $this->repository->getDocumentIdsForTokenAndField('john', 'name');

        $this->assertCount(2, $results);
        $this->assertContains($doc1->id, $results->toArray());
        $this->assertContains($doc2->id, $results->toArray());
    }

    public function test_get_document_ids_for_token_and_cluster_query(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123', ['status' => 'active', 'role' => 'admin']);
        $doc2 = $this->createDocument('App\\Models\\User|456', ['status' => 'active', 'role' => 'doctor']);

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'john', 'name', 'John');

        $results = $this->repository->getDocumentIdsForTokenAndClusterQuery(
            'john',
            'status=active & role=admin'
        );

        $this->assertCount(1, $results);
        $this->assertContains($doc1->id, $results->toArray());
    }

    public function test_get_document_ids_for_token_field_and_cluster_query(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123', ['status' => 'active', 'role' => 'admin']);
        $doc2 = $this->createDocument('App\\Models\\User|456', ['status' => 'active', 'role' => 'doctor']);

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'john', 'name', 'John');

        $results = $this->repository->getDocumentIdsForTokenFieldAndClusterQuery(
            'john',
            'name',
            'status=active & role=admin'
        );

        $this->assertCount(1, $results);
        $this->assertContains($doc1->id, $results->toArray());
    }

    // ============================================================================
    // TESTS - Count Methods
    // ============================================================================

    public function test_count_distinct_tokens(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $count = $this->repository->countDistinctTokens();
        $this->assertSame(2, $count);
    }

    public function test_count_by_type(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John', GramType::LEXICAL);
        $this->createToken($doc->id, 'JN', 'name', 'John', GramType::METAPHONE);
        $this->createToken($doc->id, 'jane', 'name', 'Jane', GramType::LEXICAL);

        $count = $this->repository->countByType(GramType::LEXICAL);

        $this->assertSame(2, $count);
    }

    public function test_count_by_field(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');
        $this->createToken($doc->id, 'john', 'email', 'John');

        $count = $this->repository->countByField('name');

        $this->assertSame(2, $count);
    }

    public function test_count_by_namespace(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123');
        $doc2 = $this->createDocument('App\\Models\\Product|456');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'doe', 'name', 'Doe');
        $this->createToken($doc2->id, 'laptop', 'name', 'Laptop');

        $count = $this->repository->countByNamespace('App\\Models\\User');

        $this->assertSame(2, $count);
    }

    public function test_count_by_cluster_query(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123', ['status' => 'active', 'role' => 'admin']);
        $doc2 = $this->createDocument('App\\Models\\User|456', ['status' => 'inactive', 'role' => 'doctor']);

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'jane', 'name', 'Jane');

        $count = $this->repository->countByClusterQuery('status=active & role=admin');

        $this->assertSame(1, $count);
    }

    // ============================================================================
    // TESTS - Delete Methods
    // ============================================================================

    public function test_delete_by_document_id(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');

        $count = $this->repository->deleteByDocumentId($doc->id);
        $this->assertSame(2, $count);

        $tokens = $this->repository->findByDocumentId($doc->id);
        $this->assertCount(0, $tokens);
    }

    public function test_delete_by_document_fingerprint(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');

        $fingerprint = new IndexableFingerprintVO('App\\Models\\User|123');
        $count = $this->repository->deleteByDocumentFingerPrint($fingerprint);
        $this->assertSame(2, $count);

        $tokens = $this->repository->findByDocumentId($doc->id);
        $this->assertCount(0, $tokens);
    }

    public function test_delete_by_namespace(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123');
        $doc2 = $this->createDocument('App\\Models\\Product|456');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc1->id, 'doe', 'name', 'Doe');
        $this->createToken($doc2->id, 'laptop', 'name', 'Laptop');

        $count = $this->repository->deleteByNamespace('App\\Models\\User');
        $this->assertSame(2, $count);

        $tokens = $this->repository->findByNamespace('App\\Models\\User');
        $this->assertCount(0, $tokens);
    }

    public function test_delete_by_cluster_query(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123', ['status' => 'active', 'role' => 'admin']);
        $doc2 = $this->createDocument('App\\Models\\User|456', ['status' => 'inactive', 'role' => 'doctor']);

        $token1 = $this->createToken($doc1->id, 'john', 'name', 'John');
        $token2 = $this->createToken($doc2->id, 'jane', 'name', 'Jane');

        $count = $this->repository->deleteByClusterQuery('status=active & role=admin');
        $this->assertSame(1, $count);

        $found = $this->repository->find($token1->id);
        $this->assertNull($found);

        $found = $this->repository->find($token2->id);
        $this->assertNotNull($found);
    }

    public function test_delete_by_token(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $count = $this->repository->deleteByToken('john');
        $this->assertSame(2, $count);

        $tokens = $this->repository->findByToken('john');
        $this->assertCount(0, $tokens);
    }

    public function test_delete_by_token_and_field(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');

        $count = $this->repository->deleteByTokenAndField('john', 'email');
        $this->assertSame(1, $count);

        $tokens = $this->repository->findByToken('john');
        $this->assertCount(1, $tokens);
        $this->assertSame('name', $tokens->first()->field);
    }

    // ============================================================================
    // TESTS - Distinct Methods
    // ============================================================================

    public function test_get_distinct_tokens(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $tokens = $this->repository->getDistinctTokens();

        $this->assertCount(2, $tokens);
        $this->assertContains('john', $tokens->toArray());
        $this->assertContains('jane', $tokens->toArray());
    }

    public function test_get_distinct_fields(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');
        $this->createToken($doc->id, 'jane', 'name', 'Jane');

        $fields = $this->repository->getDistinctFields();

        $this->assertCount(2, $fields);
        $this->assertContains('name', $fields->toArray());
        $this->assertContains('email', $fields->toArray());
    }

    // ============================================================================
    // TESTS - Frequency
    // ============================================================================

    public function test_increment_frequency(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $token = $this->createToken($doc->id, 'john', 'name', 'John');

        $this->assertSame(1, $token->frequency);

        $this->repository->incrementFrequency($token->id);

        $found = $this->repository->find($token->id);
        $this->assertSame(2, $found->frequency);

        $this->repository->incrementFrequency($token->id);

        $found = $this->repository->find($token->id);
        $this->assertSame(3, $found->frequency);
    }

    // ============================================================================
    // TESTS - AbstractRepository Methods
    // ============================================================================

    public function test_find_by_with_token_filter(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');

        $filters = new IndexedTokenFiltersRecord(
            token: 'john'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: 10,
            sortBy: new SortColumns('token:asc'),
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);

        foreach ($results as $token) {
            $this->assertInstanceOf(IndexedToken::class, $token);
            $this->assertSame('john', $token->token);
        }
    }

    public function test_find_by_with_type_filter(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John', GramType::LEXICAL);
        $this->createToken($doc->id, 'JN', 'name', 'John', GramType::METAPHONE);

        $filters = new IndexedTokenFiltersRecord(
            token_type: GramType::METAPHONE
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame(GramType::METAPHONE, $results->first()->token_type);
    }

    public function test_find_by_with_namespace_filter(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123');
        $doc2 = $this->createDocument('App\\Models\\Product|456');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'laptop', 'name', 'Laptop');

        $filters = new IndexedTokenFiltersRecord(
            namespace: 'App\\Models\\User'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('john', $results->first()->token);
    }

    public function test_find_by_with_cluster_query_filter(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123', ['status' => 'active', 'role' => 'admin']);
        $doc2 = $this->createDocument('App\\Models\\User|456', ['status' => 'inactive', 'role' => 'doctor']);

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'jane', 'name', 'Jane');

        $filters = new IndexedTokenFiltersRecord(
            cluster_query: 'status=active & role=admin'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('john', $results->first()->token);
    }

    public function test_find_by_with_cluster_queries_vo(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123', ['status' => 'active', 'role' => 'admin']);
        $doc2 = $this->createDocument('App\\Models\\User|456', ['status' => 'inactive', 'role' => 'doctor']);

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'jane', 'name', 'Jane');

        $queries = new ClusterQueries([
            'cluster' => 'status=active & role=admin',
        ]);

        $findBy = new FindByRecord(
            filters: new EmptyRecord,
            columns: SelectColumns::all(),
            clusterQueries: $queries,
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('john', $results->first()->token);
    }

    public function test_paginate_with_filters(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');

        for ($i = 1; $i <= 10; $i++) {
            $this->createToken($doc->id, "token_{$i}", 'name', "Token {$i}");
        }

        $filters = new IndexedTokenFiltersRecord(
            field: 'name'
        );

        $paginate = new PaginateRecord(
            perPage: 3,
            page: 1,
            filters: $filters,
            columns: SelectColumns::all(),
            sortBy: 'token',
        );

        $results = $this->repository->paginate($paginate);

        $this->assertCount(3, $results->items());
        $this->assertSame(10, $results->total());
        $this->assertSame(1, $results->currentPage());
    }

    // ============================================================================
    // TESTS - applyFilters (protected method tested via findBy)
    // ============================================================================

    public function test_apply_filters_with_id(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $token = $this->createToken($doc->id, 'john', 'name', 'John');

        $filters = new IndexedTokenFiltersRecord(
            id: $token->id
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame($token->id, $results->first()->id);
    }

    public function test_apply_filters_with_token(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'doe', 'name', 'Doe');

        $filters = new IndexedTokenFiltersRecord(
            token: 'john'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('john', $results->first()->token);
    }

    public function test_apply_filters_with_field(): void
    {
        $doc = $this->createDocument('App\\Models\\User|123');
        $this->createToken($doc->id, 'john', 'name', 'John');
        $this->createToken($doc->id, 'john', 'email', 'John');

        $filters = new IndexedTokenFiltersRecord(
            field: 'email'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('email', $results->first()->field);
    }

    public function test_apply_filters_with_document_ids(): void
    {
        $doc1 = $this->createDocument('App\\Models\\User|123');
        $doc2 = $this->createDocument('App\\Models\\User|456');
        $doc3 = $this->createDocument('App\\Models\\User|789');

        $this->createToken($doc1->id, 'john', 'name', 'John');
        $this->createToken($doc2->id, 'jane', 'name', 'Jane');
        $this->createToken($doc3->id, 'bob', 'name', 'Bob');

        $filters = new IndexedTokenFiltersRecord(
            document_ids: StringTypedCollection::from([
                $doc1->id,
                $doc2->id,
            ])
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(2, $results);
        $documentIds = $results->pluck('document_id')->toArray();
        $this->assertContains($doc1->id, $documentIds);
        $this->assertContains($doc2->id, $documentIds);
        $this->assertNotContains($doc3->id, $documentIds);
    }

    // ============================================================================
    // TESTS - getModel
    // ============================================================================

    public function test_get_model_returns_model_instance(): void
    {
        $model = $this->repository->getModel();

        $this->assertInstanceOf(IndexedToken::class, $model);
    }
}
