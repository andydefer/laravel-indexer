<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Repositories;

use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentFiltersRecord;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Support\Collection;

final class IndexedDocumentRepositoryTest extends IntegrationTestCase
{
    private IndexedDocumentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new IndexedDocumentRepository;
    }

    private function createUserDocument(string $id, string $name, string $email): IndexedDocument
    {
        $fingerprint = 'App.Models.User|'.$id;
        $record = IndexedDocumentRecord::from([
            'fingerprint' => $fingerprint,
            'cluster' => 'model-User|tenant-company_abc|env-production',
            'data' => [
                'name' => $name,
                'email' => $email,
            ],
        ]);

        return $this->repository->create($record);
    }

    private function createProductDocument(string $id, string $name, float $price): IndexedDocument
    {
        $fingerprint = 'App.Models.Product|'.$id;
        $record = IndexedDocumentRecord::from([
            'fingerprint' => $fingerprint,
            'cluster' => 'model-Product|category-electronics|env-production',
            'data' => [
                'name' => $name,
                'price' => $price,
            ],
        ]);

        return $this->repository->create($record);
    }

    // ==================== TESTS CREATE ====================

    public function test_create_persists_document(): void
    {
        $fingerprint = 'App.Models.User|123';
        $cluster = 'model-User|tenant-company_abc';
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $record = IndexedDocumentRecord::from([
            'fingerprint' => $fingerprint,
            'cluster' => $cluster,
            'data' => $data,
        ]);

        $document = $this->repository->create($record);

        $this->assertInstanceOf(IndexedDocument::class, $document);
        $this->assertNotNull($document->id);
        $this->assertEquals($fingerprint, $document->fingerprint);
        $this->assertEquals($cluster, $document->cluster);
        $this->assertEquals($data, $document->data);

        $found = $this->repository->find($document->id);
        $this->assertNotNull($found);
        $this->assertInstanceOf(IndexedDocument::class, $found);
        $this->assertEquals($fingerprint, $found->fingerprint);
    }

    public function test_create_raw_persists_document(): void
    {
        $fingerprint = 'App.Models.User|456';
        $data = [
            'fingerprint' => $fingerprint,
            'cluster' => 'model-User|tenant-company_xyz',
            'data' => json_encode(['name' => 'Jane Doe']),
        ];

        $document = $this->repository->createRaw($data);

        $this->assertInstanceOf(IndexedDocument::class, $document);
        $this->assertNotNull($document->id);
        $this->assertEquals($fingerprint, $document->fingerprint);
        $this->assertEquals('model-User|tenant-company_xyz', $document->cluster);
    }

    // ==================== TESTS FIND ====================

    public function test_find_returns_document(): void
    {
        $created = $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $found = $this->repository->find($created->id);

        $this->assertNotNull($found);
        $this->assertInstanceOf(IndexedDocument::class, $found);
        $this->assertEquals($created->id, $found->id);
        $this->assertEquals('App.Models.User|123', $found->fingerprint);
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $found = $this->repository->find('non-existent-id');
        $this->assertNull($found);
    }

    // ==================== TESTS FIND BY FINGERPRINT ====================

    public function test_find_by_fingerprint_returns_document(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
        $found = $this->repository->findByFingerPrint($fingerPrint);

        $this->assertNotNull($found);
        $this->assertInstanceOf(IndexedDocument::class, $found);
        $this->assertEquals('App.Models.User|123', $found->fingerprint);
    }

    public function test_find_by_fingerprint_returns_null_when_not_found(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|999');
        $found = $this->repository->findByFingerPrint($fingerPrint);
        $this->assertNull($found);
    }

    public function test_find_by_fingerprint_string_returns_document(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $found = $this->repository->findByFingerprintString('App.Models.User|123');

        $this->assertNotNull($found);
        $this->assertInstanceOf(IndexedDocument::class, $found);
        $this->assertEquals('App.Models.User|123', $found->fingerprint);
    }

    // ==================== TESTS FIND BY NAMESPACE ====================

    public function test_find_by_namespace_returns_collection(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        $results = $this->repository->findByNamespace('App.Models.User');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $doc) {
            $this->assertInstanceOf(IndexedDocument::class, $doc);
            $this->assertStringStartsWith('App.Models.User|', $doc->fingerprint);
        }
    }

    public function test_find_by_namespace_returns_empty_collection_when_none(): void
    {
        $this->createProductDocument('789', 'Laptop', 999.99);

        $results = $this->repository->findByNamespace('App.Models.User');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(0, $results);
    }

    // ==================== TESTS FIND BY CLUSTER ====================

    public function test_find_by_cluster_returns_collection(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        // Recherche par cluster complet
        $cluster = new ClusterVO('model-User|tenant-company_abc|env-production');
        $results = $this->repository->findByCluster($cluster);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $doc) {
            $this->assertInstanceOf(IndexedDocument::class, $doc);
            $this->assertEquals('model-User|tenant-company_abc|env-production', $doc->cluster);
        }
    }

    public function test_find_by_cluster_key_value_returns_collection(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');

        $results = $this->repository->findByClusterKeyValue('model', 'User');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $doc) {
            $this->assertInstanceOf(IndexedDocument::class, $doc);
            $this->assertStringContainsString('model-User', $doc->cluster);
        }
    }

    // ==================== TESTS FIND BY IDS ====================

    public function test_find_by_ids_returns_collection(): void
    {
        $doc1 = $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $doc2 = $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $doc3 = $this->createProductDocument('789', 'Laptop', 999.99);

        $results = $this->repository->findByIds([$doc1->id, $doc2->id]);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $doc) {
            $this->assertInstanceOf(IndexedDocument::class, $doc);
        }

        $ids = $results->pluck('id')->toArray();
        $this->assertContains($doc1->id, $ids);
        $this->assertContains($doc2->id, $ids);
        $this->assertNotContains($doc3->id, $ids);
    }

    public function test_find_by_ids_returns_empty_collection_when_empty_array(): void
    {
        $results = $this->repository->findByIds([]);
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(0, $results);
    }

    // ==================== TESTS UPDATE ====================

    public function test_update_updates_document(): void
    {
        $created = $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $updatedRecord = IndexedDocumentRecord::from([
            'fingerprint' => 'App.Models.User|123',
            'cluster' => 'model-User|tenant-company_xyz|env-production',
            'data' => [
                'name' => 'John Updated',
                'email' => 'john.updated@example.com',
            ],
        ]);

        $updated = $this->repository->update($created->id, $updatedRecord);

        $this->assertInstanceOf(IndexedDocument::class, $updated);
        $this->assertEquals($created->id, $updated->id);
        $this->assertEquals('App.Models.User|123', $updated->fingerprint);
        $this->assertEquals('model-User|tenant-company_xyz|env-production', $updated->cluster);
        $this->assertEquals(
            ['name' => 'John Updated', 'email' => 'john.updated@example.com'],
            $updated->data
        );
    }

    public function test_update_raw_updates_document(): void
    {
        $created = $this->createUserDocument('123', 'John Doe', 'john@example.com');

        // ✅ PASSER UN TABLEAU, PAS DU JSON ENCODE
        $data = [
            'data' => ['name' => 'John Raw Updated'],
        ];

        $updated = $this->repository->updateRaw($created->id, $data);

        $this->assertInstanceOf(IndexedDocument::class, $updated);
        $this->assertEquals($created->id, $updated->id);
        $this->assertEquals(['name' => 'John Raw Updated'], $updated->data);
    }

    // ==================== TESTS DELETE ====================

    public function test_delete_removes_document(): void
    {
        $created = $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $result = $this->repository->delete($created->id);
        $this->assertTrue($result);

        $found = $this->repository->find($created->id);
        $this->assertNull($found);
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        $result = $this->repository->delete('non-existent-id');
        $this->assertFalse($result);
    }

    public function test_delete_by_fingerprint_removes_document(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
        $count = $this->repository->deleteByFingerPrint($fingerPrint);
        $this->assertEquals(1, $count);

        $found = $this->repository->findByFingerPrint($fingerPrint);
        $this->assertNull($found);
    }

    public function test_delete_by_fingerprint_string_removes_document(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $count = $this->repository->deleteByFingerprintString('App.Models.User|123');
        $this->assertEquals(1, $count);

        $found = $this->repository->findByFingerprintString('App.Models.User|123');
        $this->assertNull($found);
    }

    public function test_delete_by_namespace_removes_all_documents(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        $count = $this->repository->deleteByNamespace('App.Models.User');
        $this->assertEquals(2, $count);

        $userResults = $this->repository->findByNamespace('App.Models.User');
        $this->assertCount(0, $userResults);

        $productResults = $this->repository->findByNamespace('App.Models.Product');
        $this->assertCount(1, $productResults);
    }

    public function test_delete_by_cluster_removes_documents(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        // Utiliser le cluster complet
        $cluster = new ClusterVO('model-User|tenant-company_abc|env-production');
        $count = $this->repository->deleteByCluster($cluster);
        $this->assertEquals(2, $count);

        $userResults = $this->repository->findByNamespace('App.Models.User');
        $this->assertCount(0, $userResults);

        $productResults = $this->repository->findByNamespace('App.Models.Product');
        $this->assertCount(1, $productResults);
    }

    public function test_delete_by_cluster_key_value_removes_documents(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');

        $count = $this->repository->deleteByClusterKeyValue('model', 'User');
        $this->assertEquals(2, $count);

        $userResults = $this->repository->findByNamespace('App.Models.User');
        $this->assertCount(0, $userResults);
    }

    // ==================== TESTS COUNTS ====================

    public function test_count_by_namespace(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        $count = $this->repository->countByNamespace('App.Models.User');
        $this->assertEquals(2, $count);

        $count = $this->repository->countByNamespace('App.Models.Product');
        $this->assertEquals(1, $count);
    }

    public function test_count_by_cluster(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        // Utiliser le cluster complet
        $cluster = new ClusterVO('model-User|tenant-company_abc|env-production');
        $count = $this->repository->countByCluster($cluster);
        $this->assertEquals(2, $count);
    }

    public function test_count_with_filters(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        $filters = new IndexedDocumentFiltersRecord(
            namespace: 'App.Models.User'
        );

        $count = $this->repository->count($filters);
        $this->assertEquals(2, $count);
    }

    // ==================== TESTS EXISTS ====================

    public function test_exists_returns_true_when_found(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $filters = new IndexedDocumentFiltersRecord(
            fingerprint: 'App.Models.User|123'
        );

        $exists = $this->repository->exists($filters);
        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_not_found(): void
    {
        $filters = new IndexedDocumentFiltersRecord(
            fingerprint: 'App.Models.User|999'
        );

        $exists = $this->repository->exists($filters);
        $this->assertFalse($exists);
    }

    public function test_exists_by_fingerprint_returns_true(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
        $exists = $this->repository->existsByFingerPrint($fingerPrint);
        $this->assertTrue($exists);
    }

    public function test_exists_by_namespace_returns_true(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');

        $exists = $this->repository->existsByNamespace('App.Models.User');
        $this->assertTrue($exists);
    }

    public function test_exists_by_cluster_returns_true(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');

        // Utiliser le cluster complet
        $cluster = new ClusterVO('model-User|tenant-company_abc|env-production');
        $exists = $this->repository->existsByCluster($cluster);
        $this->assertTrue($exists);
    }

    // ==================== TESTS DISTINCT ====================

    public function test_get_distinct_namespaces(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        $namespaces = $this->repository->getDistinctNamespaces();

        $this->assertInstanceOf(Collection::class, $namespaces);
        $this->assertCount(2, $namespaces);
        $this->assertContains('App.Models.User', $namespaces->toArray());
        $this->assertContains('App.Models.Product', $namespaces->toArray());
    }

    public function test_get_distinct_cluster_keys(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        $keys = $this->repository->getDistinctClusterKeys();

        $this->assertInstanceOf(Collection::class, $keys);
        $this->assertContains('model', $keys->toArray());
        $this->assertContains('tenant', $keys->toArray());
        $this->assertContains('env', $keys->toArray());
        $this->assertContains('category', $keys->toArray());
    }

    public function test_get_distinct_cluster_values(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');

        $values = $this->repository->getDistinctClusterValues('model');

        $this->assertInstanceOf(Collection::class, $values);
        $this->assertContains('User', $values->toArray());
    }

    // ==================== TESTS DELETE BULK ====================

    public function test_delete_bulk_removes_matching_documents(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        $filters = new IndexedDocumentFiltersRecord(
            namespace: 'App.Models.User'
        );

        $count = $this->repository->deleteBulk($filters);
        $this->assertEquals(2, $count);

        $userResults = $this->repository->findByNamespace('App.Models.User');
        $this->assertCount(0, $userResults);

        $productResults = $this->repository->findByNamespace('App.Models.Product');
        $this->assertCount(1, $productResults);
    }

    // ==================== TESTS FIND BY WITH FILTERS ====================

    public function test_find_by_with_filters_and_sort(): void
    {
        $this->createUserDocument('123', 'Alice', 'alice@example.com');
        $this->createUserDocument('456', 'Bob', 'bob@example.com');
        $this->createUserDocument('789', 'Charlie', 'charlie@example.com');

        $filters = new IndexedDocumentFiltersRecord(
            namespace: 'App.Models.User'
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: 2,
            sortBy: new SortColumns('fingerprint:asc')
        );

        $results = $this->repository->findBy($findBy);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);
        $this->assertEquals('App.Models.User|123', $results->first()->fingerprint);

        foreach ($results as $doc) {
            $this->assertInstanceOf(IndexedDocument::class, $doc);
        }
    }

    public function test_find_by_with_cluster_filter(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createUserDocument('456', 'Jane Smith', 'jane@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        // Utiliser le cluster complet
        $cluster = new ClusterVO('model-User|tenant-company_abc|env-production');
        $filters = new IndexedDocumentFiltersRecord(
            cluster: $cluster
        );

        $findBy = new FindByRecord(
            filters: $filters,
            limit: 10
        );

        $results = $this->repository->findBy($findBy);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $doc) {
            $this->assertInstanceOf(IndexedDocument::class, $doc);
            $this->assertEquals('model-User|tenant-company_abc|env-production', $doc->cluster);
        }
    }

    // ==================== TESTS FIND ALL WITH TOKENS ====================

    public function test_find_all_with_tokens_loads_relation(): void
    {
        $this->createUserDocument('123', 'John Doe', 'john@example.com');
        $this->createProductDocument('789', 'Laptop', 999.99);

        $results = $this->repository->findAllWithTokens();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertGreaterThanOrEqual(2, $results->count());

        foreach ($results as $doc) {
            $this->assertInstanceOf(IndexedDocument::class, $doc);
            $this->assertTrue($doc->relationLoaded('tokens'));
        }
    }
}
