<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services\Composants;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Records\IndexableRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexWriterTest extends IntegrationTestCase
{
    private IndexWriter $indexWriter;

    private IndexedDocumentRepository $documentRepository;

    private IndexedTokenRepository $tokenRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexWriter = $this->app->make(IndexWriter::class);
        $this->documentRepository = $this->app->make(IndexedDocumentRepository::class);
        $this->tokenRepository = $this->app->make(IndexedTokenRepository::class);
    }

    // ==================== TESTS ====================

    public function test_index_creates_document_and_tokens(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $record = new IndexableRecord(
            finger_print: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $this->assertNotNull($document);
        $this->assertEquals('App.Models.User|123', $document->fingerprint);
        $this->assertEquals('model:User|tenant:company_abc|env:production', $document->cluster);

        $tokens = $this->tokenRepository->findByDocumentId($document->id);
        $this->assertNotEmpty($tokens);

        $johnToken = $tokens->first(function ($token) {
            return $token->token === 'john' && $token->field === 'name';
        });
        $this->assertNotNull($johnToken);
        $this->assertEquals('John', $johnToken->original_text);
        $this->assertEquals(1, $johnToken->frequency);
    }

    public function test_index_increments_frequency_on_existing_token(): void
    {
        $fingerPrint1 = new IndexableFingerPrintVO('App.Models.User|456');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => 'John Doe',
        ]);

        $record1 = new IndexableRecord(
            finger_print: $fingerPrint1,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record1);

        $document1 = $this->documentRepository->findByFingerPrint($fingerPrint1);
        $token = $this->tokenRepository->findByTokenFieldAndDocument(
            'john',
            'name',
            $document1->id,
            GramType::LEXICAL
        );
        $this->assertEquals(1, $token->frequency);

        $fingerPrint2 = new IndexableFingerPrintVO('App.Models.User|789');
        $record2 = new IndexableRecord(
            finger_print: $fingerPrint2,
            data: StrictAssociative::from([
                'name' => 'John Doe',
            ]),
            cluster: $cluster,
        );

        $this->indexWriter->index($record2);

        $document2 = $this->documentRepository->findByFingerPrint($fingerPrint2);
        $token2 = $this->tokenRepository->findByTokenFieldAndDocument(
            'john',
            'name',
            $document2->id,
            GramType::LEXICAL
        );
        $this->assertEquals(1, $token2->frequency);
    }

    public function test_index_handles_nested_data(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|789');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'profile' => [
                'bio' => 'Software Developer',
                'social' => [
                    'twitter' => '@johndoe',
                    'github' => 'johndoe',
                ],
            ],
        ]);

        $record = new IndexableRecord(
            finger_print: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $fields = $tokens->pluck('field')->unique()->toArray();
        $this->assertContains('name', $fields);
        $this->assertContains('profile.bio', $fields);
        $this->assertContains('profile.social.twitter', $fields);
        $this->assertContains('profile.social.github', $fields);

        $devToken = $tokens->first(function ($token) {
            return $token->field === 'profile.bio' && $token->token === 'de';
        });
        $this->assertNotNull($devToken);
        $this->assertEquals('Developer', $devToken->original_text);

        $velToken = $tokens->first(function ($token) {
            return $token->field === 'profile.bio' && $token->token === 'vel';
        });
        $this->assertNotNull($velToken);
        $this->assertEquals('Developer', $velToken->original_text);
    }

    public function test_index_handles_array_values(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.Product|123');
        $cluster = new ClusterVO('model:Product|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => 'Laptop Pro',
            'tags' => ['php', 'laravel', 'vuejs'],
            'specs' => [
                'ram' => '16GB',
                'storage' => '512GB',
            ],
        ]);

        $record = new IndexableRecord(
            finger_print: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $tagTokens = $tokens->filter(function ($token) {
            return $token->field === 'tags';
        });

        $phpExists = $tagTokens->first(function ($token) {
            return $token->token === 'php' || str_contains($token->original_text, 'php');
        });
        $this->assertNotNull($phpExists, 'php non trouvé dans les tags');

        $laravelExists = $tagTokens->first(function ($token) {
            return $token->token === 'laravel' || str_contains($token->original_text, 'laravel');
        });
        $this->assertNotNull($laravelExists, 'laravel non trouvé dans les tags');

        $vuejsExists = $tagTokens->first(function ($token) {
            return $token->token === 'vuejs' || str_contains($token->original_text, 'vuejs');
        });
        $this->assertNotNull($vuejsExists, 'vuejs non trouvé dans les tags');
    }

    public function test_index_ignores_non_string_values(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|999');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'age' => 30,
            'active' => true,
            'score' => 99.99,
            'tags' => null,
        ]);

        $record = new IndexableRecord(
            finger_print: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $fields = $tokens->pluck('field')->unique()->toArray();
        $this->assertContains('name', $fields);
        $this->assertNotContains('age', $fields);
        $this->assertNotContains('active', $fields);
        $this->assertNotContains('score', $fields);
        $this->assertNotContains('tags', $fields);
    }

    public function test_index_uses_config_ngram_sizes(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|111');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => 'John',
        ]);

        $record = new IndexableRecord(
            finger_print: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $lexicalTokens = $tokens->filter(function ($token) {
            return $token->token_type === GramType::LEXICAL && $token->field === 'name';
        });

        $tokensList = $lexicalTokens->pluck('token')->toArray();

        $this->assertContains('jo', $tokensList);
        $this->assertContains('oh', $tokensList);
        $this->assertContains('hn', $tokensList);
        $this->assertContains('joh', $tokensList);
        $this->assertContains('ohn', $tokensList);
        $this->assertContains('john', $tokensList);

        $this->assertNotContains('j', $tokensList);
        $this->assertNotContains('o', $tokensList);
        $this->assertNotContains('h', $tokensList);
        $this->assertNotContains('n', $tokensList);
    }

    public function test_index_many_handles_multiple_records(): void
    {
        $records = new IndexableRecordCollection;

        $record1 = new IndexableRecord(
            finger_print: new IndexableFingerPrintVO('App.Models.User|1'),
            data: StrictAssociative::from(['name' => 'User 1']),
            cluster: new ClusterVO('model:User|tenant:company_abc|env:production'),
        );
        $records->add($record1);

        $record2 = new IndexableRecord(
            finger_print: new IndexableFingerPrintVO('App.Models.User|2'),
            data: StrictAssociative::from(['name' => 'User 2']),
            cluster: new ClusterVO('model:User|tenant:company_abc|env:production'),
        );
        $records->add($record2);

        $this->indexWriter->indexMany($records);

        $doc1 = $this->documentRepository->findByFingerPrint(new IndexableFingerPrintVO('App.Models.User|1'));
        $doc2 = $this->documentRepository->findByFingerPrint(new IndexableFingerPrintVO('App.Models.User|2'));

        $this->assertNotNull($doc1);
        $this->assertNotNull($doc2);

        $tokens1 = $this->tokenRepository->findByDocumentId($doc1->id);
        $tokens2 = $this->tokenRepository->findByDocumentId($doc2->id);

        $this->assertNotEmpty($tokens1);
        $this->assertNotEmpty($tokens2);
    }
}
