<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services\Composants;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\Records\IndexableRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Services\Composants\IndexDeleter;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexDeleterTest extends IntegrationTestCase
{
    private IndexWriter $indexWriter;

    private IndexDeleter $indexDeleter;

    private IndexedDocumentRepository $documentRepository;

    private IndexedTokenRepository $tokenRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexWriter = $this->app->make(IndexWriter::class);
        $this->indexDeleter = $this->app->make(IndexDeleter::class);
        $this->documentRepository = $this->app->make(IndexedDocumentRepository::class);
        $this->tokenRepository = $this->app->make(IndexedTokenRepository::class);
    }

    private function createAndIndexDocument(
        string $fingerprint,
        array $data,
        string $cluster = 'model:User|tenant:company_abc|env:production'
    ): void {
        $fingerPrint = new IndexableFingerPrintVO($fingerprint);
        $clusterVO = new ClusterVO($cluster);
        $record = new IndexableRecord(
            finger_print: $fingerPrint,
            data: StrictAssociative::from($data),
            cluster: $clusterVO,
        );

        $this->indexWriter->index($record);
    }

    // ==================== TESTS DELETE ====================

    public function test_delete_removes_document_and_tokens(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', ['name' => 'John Doe']);

        $doc = $this->documentRepository->findByFingerprintString('App.Models.User|123');
        $this->assertNotNull($doc);

        $tokens = $this->tokenRepository->findByDocumentId($doc->id);
        $this->assertNotEmpty($tokens);

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
        $this->indexDeleter->delete($fingerPrint);

        $doc = $this->documentRepository->findByFingerprintString('App.Models.User|123');
        $this->assertNull($doc);

        $tokens = $this->tokenRepository->findByDocumentId($doc?->id ?? '');
        $this->assertEmpty($tokens);
    }

    public function test_delete_does_nothing_when_document_not_exists(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|999');
        $this->indexDeleter->delete($fingerPrint);

        $this->assertTrue(true);
    }

    // ==================== TESTS DELETE MANY ====================

    public function test_delete_many_removes_multiple_documents(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', ['name' => 'John Doe']);
        $this->createAndIndexDocument('App.Models.User|456', ['name' => 'Jane Smith']);
        $this->createAndIndexDocument('App.Models.Product|789', ['name' => 'Laptop']);

        $doc1 = $this->documentRepository->findByFingerprintString('App.Models.User|123');
        $doc2 = $this->documentRepository->findByFingerprintString('App.Models.User|456');
        $doc3 = $this->documentRepository->findByFingerprintString('App.Models.Product|789');
        $this->assertNotNull($doc1);
        $this->assertNotNull($doc2);
        $this->assertNotNull($doc3);

        $collection = new IndexableFingerPrintVOCollection;
        $collection->add(new IndexableFingerPrintVO('App.Models.User|123'));
        $collection->add(new IndexableFingerPrintVO('App.Models.User|456'));

        $this->indexDeleter->deleteMany($collection);

        $doc1 = $this->documentRepository->findByFingerprintString('App.Models.User|123');
        $doc2 = $this->documentRepository->findByFingerprintString('App.Models.User|456');
        $doc3 = $this->documentRepository->findByFingerprintString('App.Models.Product|789');

        $this->assertNull($doc1);
        $this->assertNull($doc2);
        $this->assertNotNull($doc3);
    }

    public function test_delete_many_does_nothing_when_collection_empty(): void
    {
        $collection = new IndexableFingerPrintVOCollection;
        $this->indexDeleter->deleteMany($collection);

        $this->assertTrue(true);
    }

    // ==================== TESTS CLEAR ====================

    public function test_clear_removes_all_documents_and_tokens(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', ['name' => 'John Doe']);
        $this->createAndIndexDocument('App.Models.User|456', ['name' => 'Jane Smith']);
        $this->createAndIndexDocument('App.Models.Product|789', ['name' => 'Laptop']);

        $count = $this->documentRepository->getModel()->newQuery()->count();
        $this->assertEquals(3, $count);

        $tokenCount = $this->tokenRepository->getModel()->newQuery()->count();
        $this->assertGreaterThan(0, $tokenCount);

        $this->indexDeleter->clear();

        $count = $this->documentRepository->getModel()->newQuery()->count();
        $this->assertEquals(0, $count);

        $tokenCount = $this->tokenRepository->getModel()->newQuery()->count();
        $this->assertEquals(0, $tokenCount);
    }

    public function test_clear_does_nothing_when_no_data(): void
    {
        $this->indexDeleter->clear();

        $count = $this->documentRepository->getModel()->newQuery()->count();
        $this->assertEquals(0, $count);

        $this->assertTrue(true);
    }
}
