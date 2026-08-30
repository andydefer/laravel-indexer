<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Repositories;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\EmptyRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use AndyDefer\Repository\ValueObjects\SelectColumns;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class IndexedDocumentRepositoryTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private IndexedDocumentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new IndexedDocumentRepository;
    }

    // ============================================================================
    // HELPERS
    // ============================================================================

    private function createDocument(array $data = []): IndexedDocument
    {
        $id = $data['id'] ?? (string) Str::uuid();
        $namespace = $data['namespace'] ?? TestUser::class;
        $fingerprint = $namespace.'|'.$id;

        return IndexedDocument::create([
            'id' => $id,
            'fingerprint' => $fingerprint,
            'cluster' => $data['cluster'] ?? ['status' => 'active', 'role' => 'admin'],
            'data' => $data['data'] ?? ['name' => 'John Doe', 'email' => 'john@example.com'],
        ]);
    }

    private function createDocuments(int $count, array $data = []): Collection
    {
        $documents = [];
        for ($i = 0; $i < $count; $i++) {
            $documents[] = $this->createDocument($data);
        }

        return collect($documents);
    }

    private function createUserDocument(string $id, string $name, string $email): IndexedDocument
    {
        $fingerprint = TestUser::class.'|'.$id;

        return IndexedDocument::create([
            'id' => $id,
            'fingerprint' => $fingerprint,
            'cluster' => ['model' => 'User', 'tenant' => 'company_abc', 'env' => 'production', 'role' => 'admin'],
            'data' => ['name' => $name, 'email' => $email],
        ]);
    }

    private function createProductDocument(string $id, string $name, float $price): IndexedDocument
    {
        $fingerprint = TestProduct::class.'|'.$id;

        return IndexedDocument::create([
            'id' => $id,
            'fingerprint' => $fingerprint,
            'cluster' => ['model' => 'Product', 'category' => 'electronics', 'env' => 'production'],
            'data' => ['name' => $name, 'price' => $price],
        ]);
    }

    // ============================================================================
    // TESTS - Create
    // ============================================================================

    public function test_create_persists_document(): void
    {
        $id = '123';
        $fingerprint = TestUser::class.'|'.$id;
        $cluster = ['model' => 'User', 'tenant' => 'company_abc', 'role' => 'admin'];
        $data = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $record = new IndexedDocumentRecord(
            fingerprint: new IndexableFingerprintVO($fingerprint),
            cluster: new ClusterVO($cluster),
            data: StrictAssociative::from($data),
        );

        $document = $this->repository->create($record);

        $this->assertInstanceOf(IndexedDocument::class, $document);
        $this->assertNotNull($document->id);
        $this->assertSame($fingerprint, $document->fingerprint->getValue());
        $this->assertSame('admin', $document->cluster->get('role'));
        $this->assertSame('john@example.com', $document->data->get('email'));

        $found = $this->repository->find($document->id);
        $this->assertNotNull($found);
        $this->assertSame($fingerprint, $found->fingerprint->getValue());
    }

    // ============================================================================
    // TESTS - Find Methods
    // ============================================================================

    public function test_find_by_finger_print_returns_document(): void
    {
        $id = '123';
        $document = $this->createDocument(['id' => $id, 'namespace' => TestUser::class]);

        $result = $this->repository->findByFingerPrint(
            new IndexableFingerprintVO(TestUser::class.'|'.$id)
        );

        $this->assertNotNull($result);
        $this->assertSame($document->id, $result->id);
    }

    public function test_find_by_finger_print_returns_null_when_not_found(): void
    {
        $result = $this->repository->findByFingerPrint(
            new IndexableFingerprintVO(TestUser::class.'|999')
        );

        $this->assertNull($result);
    }

    public function test_find_by_fingerprint_string_returns_document(): void
    {
        $id = '123';
        $document = $this->createDocument(['id' => $id, 'namespace' => TestUser::class]);

        $result = $this->repository->findByFingerprintString(TestUser::class.'|'.$id);

        $this->assertNotNull($result);
        $this->assertSame($document->id, $result->id);
    }

    public function test_find_by_namespace_returns_documents(): void
    {
        $this->createDocument(['id' => '1', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '2', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '3', 'namespace' => TestProduct::class]);

        $results = $this->repository->findByNamespace(TestUser::class);

        $this->assertCount(2, $results);
        $this->assertStringContainsString('User', $results->first()->fingerprint->getValue());
    }

    public function test_find_by_cluster_query_returns_filtered_documents(): void
    {
        $this->createDocument(['cluster' => ['status' => 'active', 'role' => 'admin']]);
        $this->createDocument(['cluster' => ['status' => 'inactive', 'role' => 'doctor']]);
        $this->createDocument(['cluster' => ['status' => 'active', 'role' => 'doctor']]);

        $results = $this->repository->findByClusterQuery('status=active & role=admin');

        $this->assertCount(1, $results);
        $this->assertSame('admin', $results->first()->cluster->get('role'));
    }

    public function test_find_by_cluster_query_with_role(): void
    {
        $this->createDocument(['cluster' => ['role' => 'admin', 'status' => 'active']]);
        $this->createDocument(['cluster' => ['role' => 'doctor', 'status' => 'active']]);
        $this->createDocument(['cluster' => ['role' => 'admin', 'status' => 'inactive']]);

        $results = $this->repository->findByClusterQuery('role=admin');

        $this->assertCount(2, $results);
        $this->assertSame('admin', $results->first()->cluster->get('role'));
        $this->assertSame('admin', $results->get(1)->cluster->get('role'));
    }

    public function test_find_by_ids_returns_documents(): void
    {
        $doc1 = $this->createDocument();
        $doc2 = $this->createDocument();
        $this->createDocument();

        $results = $this->repository->findByIds([$doc1->id, $doc2->id]);

        $this->assertCount(2, $results);
        $this->assertContains($doc1->id, $results->pluck('id')->toArray());
        $this->assertContains($doc2->id, $results->pluck('id')->toArray());
    }

    public function test_find_by_ids_returns_empty_collection_when_empty_array(): void
    {
        $results = $this->repository->findByIds([]);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(0, $results);
    }

    // ============================================================================
    // TESTS - Delete Methods
    // ============================================================================

    public function test_delete_by_finger_print_removes_document(): void
    {
        $id = '123';
        $this->createDocument(['id' => $id, 'namespace' => TestUser::class]);

        $deleted = $this->repository->deleteByFingerPrint(
            new IndexableFingerprintVO(TestUser::class.'|'.$id)
        );

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('indexed_documents', ['id' => $id]);
    }

    public function test_delete_by_fingerprint_string_removes_document(): void
    {
        $id = '123';
        $this->createDocument(['id' => $id, 'namespace' => TestUser::class]);

        $deleted = $this->repository->deleteByFingerprintString(TestUser::class.'|'.$id);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('indexed_documents', ['id' => $id]);
    }

    public function test_delete_by_namespace_removes_documents(): void
    {
        $this->createDocument(['id' => '1', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '2', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '3', 'namespace' => TestProduct::class]);

        $deleted = $this->repository->deleteByNamespace(TestUser::class);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseCount('indexed_documents', 1);
    }

    public function test_delete_by_cluster_query_removes_matching_documents(): void
    {
        $this->createDocument(['cluster' => ['status' => 'active', 'role' => 'admin']]);
        $this->createDocument(['cluster' => ['status' => 'inactive', 'role' => 'doctor']]);
        $this->createDocument(['cluster' => ['status' => 'active', 'role' => 'doctor']]);

        $deleted = $this->repository->deleteByClusterQuery('status=active & role=admin');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseCount('indexed_documents', 2);
    }

    // ============================================================================
    // TESTS - Count Methods
    // ============================================================================

    public function test_count_by_namespace_returns_correct_count(): void
    {
        $this->createDocument(['id' => '1', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '2', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '3', 'namespace' => TestProduct::class]);

        $count = $this->repository->countByNamespace(TestUser::class);

        $this->assertSame(2, $count);
    }

    public function test_count_by_cluster_query_returns_correct_count(): void
    {
        $this->createDocument(['cluster' => ['status' => 'active', 'role' => 'admin']]);
        $this->createDocument(['cluster' => ['status' => 'inactive', 'role' => 'doctor']]);
        $this->createDocument(['cluster' => ['status' => 'active', 'role' => 'doctor']]);

        $count = $this->repository->countByClusterQuery('status=active & role=admin');

        $this->assertSame(1, $count);
    }

    // ============================================================================
    // TESTS - Exists Methods
    // ============================================================================

    public function test_exists_by_finger_print_returns_true_when_exists(): void
    {
        $id = '123';
        $this->createDocument(['id' => $id, 'namespace' => TestUser::class]);

        $exists = $this->repository->existsByFingerPrint(
            new IndexableFingerprintVO(TestUser::class.'|'.$id)
        );

        $this->assertTrue($exists);
    }

    public function test_exists_by_finger_print_returns_false_when_not_exists(): void
    {
        $exists = $this->repository->existsByFingerPrint(
            new IndexableFingerprintVO(TestUser::class.'|999')
        );

        $this->assertFalse($exists);
    }

    public function test_exists_by_namespace_returns_true_when_exists(): void
    {
        $this->createDocument(['id' => '1', 'namespace' => TestUser::class]);

        $exists = $this->repository->existsByNamespace(TestUser::class);

        $this->assertTrue($exists);
    }

    public function test_exists_by_namespace_returns_false_when_not_exists(): void
    {
        $exists = $this->repository->existsByNamespace('App\\Models\\Unknown');

        $this->assertFalse($exists);
    }

    public function test_exists_by_cluster_query_returns_true_when_exists(): void
    {
        $this->createDocument(['cluster' => ['status' => 'active', 'role' => 'admin']]);

        $exists = $this->repository->existsByClusterQuery('status=active & role=admin');

        $this->assertTrue($exists);
    }

    public function test_exists_by_cluster_query_returns_false_when_not_exists(): void
    {
        $exists = $this->repository->existsByClusterQuery('status=inactive & role=admin');

        $this->assertFalse($exists);
    }

    // ============================================================================
    // TESTS - Distinct Methods
    // ============================================================================

    public function test_get_distinct_namespaces_returns_unique_namespaces(): void
    {
        $this->createDocument(['id' => '1', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '2', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '3', 'namespace' => TestProduct::class]);

        $namespaces = $this->repository->getDistinctNamespaces();

        $this->assertCount(2, $namespaces);
        $this->assertContains(TestUser::class, $namespaces->toArray());
        $this->assertContains(TestProduct::class, $namespaces->toArray());
    }

    // ============================================================================
    // TESTS - CreateMany
    // ============================================================================

    public function test_create_many_inserts_multiple_documents(): void
    {
        $records = [
            new IndexedDocumentRecord(
                fingerprint: new IndexableFingerprintVO(TestUser::class.'|1'),
                cluster: new ClusterVO(['status' => 'active']),
                data: StrictAssociative::from(['name' => 'John']),
            ),
            new IndexedDocumentRecord(
                fingerprint: new IndexableFingerprintVO(TestUser::class.'|2'),
                cluster: new ClusterVO(['status' => 'inactive']),
                data: StrictAssociative::from(['name' => 'Jane']),
            ),
        ];

        $documents = $this->repository->createMany($records);

        $this->assertCount(2, $documents);
        $this->assertDatabaseCount('indexed_documents', 2);
    }

    public function test_create_many_returns_empty_array_when_empty(): void
    {
        $documents = $this->repository->createMany([]);

        $this->assertEmpty($documents);
    }

    // ============================================================================
    // TESTS - AbstractRepository Methods
    // ============================================================================

    public function test_find_by_with_filters(): void
    {
        $doc1 = $this->createDocument(['id' => '1', 'namespace' => TestUser::class]);
        $doc2 = $this->createDocument(['id' => '2', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '3', 'namespace' => TestProduct::class]);

        $filters = new IndexedDocumentFiltersRecord(
            namespace: TestUser::class
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: 10,
            sortBy: new SortColumns('fingerprint:asc'),
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(2, $results);
        $this->assertContains($doc1->id, $results->pluck('id')->toArray());
        $this->assertContains($doc2->id, $results->pluck('id')->toArray());
    }

    public function test_find_by_with_cluster_query_filter(): void
    {
        $this->createDocument(['cluster' => ['status' => 'active', 'role' => 'admin']]);
        $this->createDocument(['cluster' => ['status' => 'inactive', 'role' => 'doctor']]);

        $filters = new IndexedDocumentFiltersRecord(
            cluster_query: 'status=active & role=admin'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: 10,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('admin', $results->first()->cluster->get('role'));
    }

    public function test_find_by_with_cluster_queries_vo(): void
    {
        $this->createDocument([
            'cluster' => ['status' => 'active', 'role' => 'admin'],
            'data' => ['type' => 'document'],
        ]);
        $this->createDocument([
            'cluster' => ['status' => 'inactive', 'role' => 'doctor'],
            'data' => ['type' => 'document'],
        ]);

        $queries = new ClusterQueries([
            'cluster' => 'status=active & role=admin',
            'data' => 'type=document',
        ]);

        $findBy = new FindByRecord(
            filters: new EmptyRecord,
            limit: 10,
            columns: SelectColumns::all(),
            clusterQueries: $queries,
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('admin', $results->first()->cluster->get('role'));
    }

    public function test_paginate_with_filters(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createDocument([
                'id' => (string) $i,
                'namespace' => TestUser::class,
                'cluster' => ['status' => $i <= 5 ? 'active' : 'inactive'],
            ]);
        }

        $filters = new IndexedDocumentFiltersRecord(
            cluster_query: 'status=active'
        );

        $paginate = new PaginateRecord(
            perPage: 3,
            page: 1,
            filters: $filters,
            columns: SelectColumns::all(),
            sortBy: 'fingerprint',
        );

        $results = $this->repository->paginate($paginate);

        $this->assertCount(3, $results->items());
        $this->assertSame(5, $results->total());
        $this->assertSame(1, $results->currentPage());
    }

    // ============================================================================
    // TESTS - applyFilters (protected method tested via findBy)
    // ============================================================================

    public function test_apply_filters_with_id(): void
    {
        $id = '123';
        $doc = $this->createDocument(['id' => $id, 'namespace' => TestUser::class]);

        $filters = new IndexedDocumentFiltersRecord(
            id: $doc->id
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame($doc->id, $results->first()->id);
    }

    public function test_apply_filters_with_fingerprint(): void
    {
        $id = '123';
        $doc = $this->createDocument(['id' => $id, 'namespace' => TestUser::class]);

        $filters = new IndexedDocumentFiltersRecord(
            fingerprint: new IndexableFingerprintVO(TestUser::class.'|'.$id)
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame($doc->id, $results->first()->id);
    }

    public function test_apply_filters_with_entity_id(): void
    {
        $this->createDocument(['id' => '123', 'namespace' => TestUser::class]);
        $this->createDocument(['id' => '456', 'namespace' => TestUser::class]);

        $filters = new IndexedDocumentFiltersRecord(
            entity_id: '123'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            columns: SelectColumns::all(),
        );

        $results = $this->repository->findBy($findBy);

        $this->assertCount(1, $results);
        $this->assertSame('123', $results->first()->fingerprint->getId());
    }

    public function test_apply_filters_with_document_ids(): void
    {
        $doc1 = $this->createDocument();
        $doc2 = $this->createDocument();
        $doc3 = $this->createDocument();

        $filters = new IndexedDocumentFiltersRecord(
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
        $this->assertContains($doc1->id, $results->pluck('id')->toArray());
        $this->assertContains($doc2->id, $results->pluck('id')->toArray());
        $this->assertNotContains($doc3->id, $results->pluck('id')->toArray());
    }

    // ============================================================================
    // TESTS - getModel
    // ============================================================================

    public function test_get_model_returns_model_instance(): void
    {
        $model = $this->repository->getModel();

        $this->assertInstanceOf(IndexedDocument::class, $model);
    }
}
