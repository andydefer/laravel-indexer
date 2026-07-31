<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexerServiceTest extends IntegrationTestCase
{
    private IndexerService $indexer;

    private IndexedDocumentRepository $documentRepository;

    private IndexedTokenRepository $tokenRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexer = $this->app->make(IndexerService::class);
        $this->documentRepository = $this->app->make(IndexedDocumentRepository::class);
        $this->tokenRepository = $this->app->make(IndexedTokenRepository::class);
    }

    private function createClusterVO(array $cluster): ClusterVO
    {
        return new ClusterVO($cluster);
    }

    private function createRecord(string $fingerprint, array $data, array $cluster = ['model' => 'User', 'tenant' => 'company_abc', 'env' => 'production']): IndexedDocumentRecord
    {
        return new IndexedDocumentRecord(
            fingerprint: new IndexableFingerPrintVO($fingerprint),
            data: StrictAssociative::from($data),
            cluster: $this->createClusterVO($cluster),
        );
    }

    // ==================== TESTS REFRESH ====================

    public function test_refresh_deletes_and_reindexes_document(): void
    {
        $record = $this->createRecord('App\Models\User|123', ['name' => 'John Doe']);
        $this->indexer->index($record);

        $doc = $this->documentRepository->findByFingerprintString('App\Models\User|123');
        $this->assertNotNull($doc);

        $tokens = $this->tokenRepository->findByDocumentId($doc->id);
        $this->assertNotEmpty($tokens);

        $updatedRecord = $this->createRecord('App\Models\User|123', ['name' => 'John Updated']);
        $this->indexer->refresh($updatedRecord);

        $doc = $this->documentRepository->findByFingerprintString('App\Models\User|123');
        $this->assertNotNull($doc);

        $this->assertEquals('John Updated', $doc->data->get('name'));

        $newTokens = $this->tokenRepository->findByDocumentId($doc->id);
        $this->assertNotEmpty($newTokens);

        $johnToken = $newTokens->first(function ($token) {
            return $token->token === 'john' && $token->field === 'name';
        });
        $this->assertNotNull($johnToken);
    }

    public function test_refresh_creates_document_when_not_exists(): void
    {
        $record = $this->createRecord('App\Models\User|999', ['name' => 'New User']);

        $doc = $this->documentRepository->findByFingerprintString('App\Models\User|999');
        $this->assertNull($doc);

        $this->indexer->refresh($record);

        $doc = $this->documentRepository->findByFingerprintString('App\Models\User|999');
        $this->assertNotNull($doc);
        $this->assertEquals('New User', $doc->data->get('name'));
    }

    // ==================== TESTS REFRESH MANY ====================

    public function test_refresh_many_deletes_and_reindexes_multiple_documents(): void
    {
        $record1 = $this->createRecord('App\Models\User|123', ['name' => 'John Doe']);
        $record2 = $this->createRecord('App\Models\User|456', ['name' => 'Jane Smith']);

        $this->indexer->index($record1);
        $this->indexer->index($record2);

        $doc1 = $this->documentRepository->findByFingerprintString('App\Models\User|123');
        $doc2 = $this->documentRepository->findByFingerprintString('App\Models\User|456');
        $this->assertNotNull($doc1);
        $this->assertNotNull($doc2);

        $updatedRecord1 = $this->createRecord('App\Models\User|123', ['name' => 'John Updated']);
        $updatedRecord2 = $this->createRecord('App\Models\User|456', ['name' => 'Jane Updated']);

        $collection = new IndexableRecordCollection;
        $collection->add($updatedRecord1);
        $collection->add($updatedRecord2);

        $this->indexer->refreshMany($collection);

        $doc1 = $this->documentRepository->findByFingerprintString('App\Models\User|123');
        $doc2 = $this->documentRepository->findByFingerprintString('App\Models\User|456');
        $this->assertNotNull($doc1);
        $this->assertNotNull($doc2);

        $this->assertEquals('John Updated', $doc1->data->get('name'));
        $this->assertEquals('Jane Updated', $doc2->data->get('name'));
    }

    public function test_refresh_many_creates_documents_when_not_exists(): void
    {
        $record1 = $this->createRecord('App\Models\User|111', ['name' => 'User 111']);
        $record2 = $this->createRecord('App\Models\User|222', ['name' => 'User 222']);

        $doc1 = $this->documentRepository->findByFingerprintString('App\Models\User|111');
        $doc2 = $this->documentRepository->findByFingerprintString('App\Models\User|222');
        $this->assertNull($doc1);
        $this->assertNull($doc2);

        $collection = new IndexableRecordCollection;
        $collection->add($record1);
        $collection->add($record2);

        $this->indexer->refreshMany($collection);

        $doc1 = $this->documentRepository->findByFingerprintString('App\Models\User|111');
        $doc2 = $this->documentRepository->findByFingerprintString('App\Models\User|222');
        $this->assertNotNull($doc1);
        $this->assertNotNull($doc2);
        $this->assertEquals('User 111', $doc1->data->get('name'));
        $this->assertEquals('User 222', $doc2->data->get('name'));
    }

    public function test_refresh_many_with_empty_collection(): void
    {
        $collection = new IndexableRecordCollection;
        $this->indexer->refreshMany($collection);

        $this->assertTrue(true);
    }

    // ==================== TESTS INDEX ET DELETE ====================

    public function test_index_creates_document_and_tokens(): void
    {
        $record = $this->createRecord('App\Models\User|789', ['name' => 'Test User']);
        $this->indexer->index($record);

        $doc = $this->documentRepository->findByFingerprintString('App\Models\User|789');
        $this->assertNotNull($doc);
        $this->assertEquals('Test User', $doc->data->get('name'));

        $tokens = $this->tokenRepository->findByDocumentId($doc->id);
        $this->assertNotEmpty($tokens);
    }

    public function test_delete_removes_document_and_tokens(): void
    {
        $record = $this->createRecord('App\Models\User|456', ['name' => 'To Delete']);
        $this->indexer->index($record);

        $doc = $this->documentRepository->findByFingerprintString('App\Models\User|456');
        $this->assertNotNull($doc);

        $tokens = $this->tokenRepository->findByDocumentId($doc->id);
        $this->assertNotEmpty($tokens);

        $this->indexer->delete(new IndexableFingerPrintVO('App\Models\User|456'));

        $doc = $this->documentRepository->findByFingerprintString('App\Models\User|456');
        $this->assertNull($doc);

        $tokens = $this->tokenRepository->findByDocumentId($doc?->id ?? '');
        $this->assertEmpty($tokens);
    }

    public function test_delete_many_removes_multiple_documents(): void
    {
        $record1 = $this->createRecord('App\Models\User|111', ['name' => 'User 1']);
        $record2 = $this->createRecord('App\Models\User|222', ['name' => 'User 2']);
        $record3 = $this->createRecord('App\Models\User|333', ['name' => 'User 3']);

        $this->indexer->index($record1);
        $this->indexer->index($record2);
        $this->indexer->index($record3);

        $doc1 = $this->documentRepository->findByFingerprintString('App\Models\User|111');
        $doc2 = $this->documentRepository->findByFingerprintString('App\Models\User|222');
        $doc3 = $this->documentRepository->findByFingerprintString('App\Models\User|333');
        $this->assertNotNull($doc1);
        $this->assertNotNull($doc2);
        $this->assertNotNull($doc3);

        $collection = new IndexableFingerPrintVOCollection;
        $collection->add(new IndexableFingerPrintVO('App\Models\User|111'));
        $collection->add(new IndexableFingerPrintVO('App\Models\User|222'));

        $this->indexer->deleteMany($collection);

        $doc1 = $this->documentRepository->findByFingerprintString('App\Models\User|111');
        $doc2 = $this->documentRepository->findByFingerprintString('App\Models\User|222');
        $doc3 = $this->documentRepository->findByFingerprintString('App\Models\User|333');
        $this->assertNull($doc1);
        $this->assertNull($doc2);
        $this->assertNotNull($doc3);
    }

    public function test_clear_removes_all_documents(): void
    {
        $record1 = $this->createRecord('App\Models\User|111', ['name' => 'User 1']);
        $record2 = $this->createRecord('App\Models\User|222', ['name' => 'User 2']);

        $this->indexer->index($record1);
        $this->indexer->index($record2);

        $count = $this->documentRepository->getModel()->newQuery()->count();
        $this->assertEquals(2, $count);

        $this->indexer->clear();

        $count = $this->documentRepository->getModel()->newQuery()->count();
        $this->assertEquals(0, $count);
    }
}
