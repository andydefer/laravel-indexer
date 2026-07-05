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
            fingerprint: $fingerPrint,
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

        $joToken = $tokens->first(function ($token) {
            return $token->token === 'jo' && $token->field === 'name';
        });
        $this->assertNull($joToken);
    }

    public function test_index_increments_frequency_on_existing_token(): void
    {
        $fingerPrint1 = new IndexableFingerPrintVO('App.Models.User|456');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => 'John Doe',
        ]);

        $record1 = new IndexableRecord(
            fingerprint: $fingerPrint1,
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
            fingerprint: $fingerPrint2,
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
            fingerprint: $fingerPrint,
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

        $soToken = $tokens->first(function ($token) {
            return $token->field === 'profile.bio' && $token->token === 'so';
        });
        $this->assertNull($soToken);

        $sofToken = $tokens->first(function ($token) {
            return $token->field === 'profile.bio' && $token->token === 'sof';
        });
        $this->assertNotNull($sofToken);
        $this->assertEquals('Software', $sofToken->original_text);

        $deToken = $tokens->first(function ($token) {
            return $token->field === 'profile.bio' && $token->token === 'de';
        });
        $this->assertNull($deToken);

        $devToken = $tokens->first(function ($token) {
            return $token->field === 'profile.bio' && $token->token === 'dev';
        });
        $this->assertNotNull($devToken);
        $this->assertEquals('Developer', $devToken->original_text);
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
            fingerprint: $fingerPrint,
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
            fingerprint: $fingerPrint,
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
            fingerprint: $fingerPrint,
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

        $this->assertNotContains('jo', $tokensList);
        $this->assertNotContains('oh', $tokensList);
        $this->assertNotContains('hn', $tokensList);

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
            fingerprint: new IndexableFingerPrintVO('App.Models.User|1'),
            data: StrictAssociative::from(['name' => 'User 1']),
            cluster: new ClusterVO('model:User|tenant:company_abc|env:production'),
        );
        $records->add($record1);

        $record2 = new IndexableRecord(
            fingerprint: new IndexableFingerPrintVO('App.Models.User|2'),
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

    // ==================== NOUVEAUX TESTS POUR LE CHUNKING ====================

    public function test_index_handles_long_text_with_chunking(): void
    {
        $longText = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.';

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|999');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'description' => $longText,
        ]);

        $record = new IndexableRecord(
            fingerprint: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $descriptionTokens = $tokens->filter(function ($token) {
            return $token->field === 'description';
        });

        $this->assertNotEmpty($descriptionTokens);

        $loremToken = $descriptionTokens->first(function ($token) {
            return $token->token === 'lorem';
        });
        $this->assertNotNull($loremToken, 'Token "lorem" devrait être indexé');
        $this->assertEquals('Lorem', $loremToken->original_text);

        $ipsumToken = $descriptionTokens->first(function ($token) {
            return $token->token === 'ipsum';
        });
        $this->assertNotNull($ipsumToken, 'Token "ipsum" devrait être indexé');
        $this->assertEquals('ipsum', $ipsumToken->original_text);

        $dolorToken = $descriptionTokens->first(function ($token) {
            return $token->token === 'dolor';
        });
        $this->assertNotNull($dolorToken, 'Token "dolor" devrait être indexé');
        $this->assertEquals('dolor', $dolorToken->original_text);
    }

    public function test_index_handles_very_long_single_word(): void
    {
        $longWord = 'Supercalifragilisticexpialidocious';

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|888');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'name' => $longWord,
        ]);

        $record = new IndexableRecord(
            fingerprint: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $nameTokens = $tokens->filter(function ($token) {
            return $token->field === 'name';
        });

        $this->assertNotEmpty($nameTokens);

        $superToken = $nameTokens->first(function ($token) {
            return str_contains($token->token, 'super');
        });
        $this->assertNotNull($superToken, 'Des n-grammes de "Supercalifragilisticexpialidocious" devraient être indexés');
    }

    public function test_index_handles_mixed_short_and_long_texts(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|777');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'short' => 'Hello World',
            'medium' => 'This is a medium text',
            'long' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        ]);

        $record = new IndexableRecord(
            fingerprint: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $fields = $tokens->pluck('field')->unique()->toArray();
        $this->assertContains('short', $fields);
        $this->assertContains('medium', $fields);
        $this->assertContains('long', $fields);

        $shortTokens = $tokens->filter(function ($token) {
            return $token->field === 'short';
        });
        $helloToken = $shortTokens->first(function ($token) {
            return $token->token === 'hello';
        });
        $this->assertNotNull($helloToken);
        $this->assertEquals('Hello', $helloToken->original_text);

        $longTokens = $tokens->filter(function ($token) {
            return $token->field === 'long';
        });
        $loremToken = $longTokens->first(function ($token) {
            return $token->token === 'lorem';
        });
        $this->assertNotNull($loremToken);
        $this->assertEquals('Lorem', $loremToken->original_text);
    }

    public function test_index_handles_text_with_special_characters(): void
    {
        $textWithSpecialChars = "L'utilisateur Jean-Pierre a acheté 2 produits à 100€ !";

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|666');
        $cluster = new ClusterVO('model:User|tenant:company_abc|env:production');
        $data = StrictAssociative::from([
            'description' => $textWithSpecialChars,
        ]);

        $record = new IndexableRecord(
            fingerprint: $fingerPrint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerPrint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $descTokens = $tokens->filter(function ($token) {
            return $token->field === 'description';
        });

        $this->assertNotEmpty($descTokens);

        // Vérifier qu'un n-gramme de 'utilisateur' existe (taille 4)
        $utilToken = $descTokens->first(function ($token) {
            return $token->token === 'util' && $token->original_text === "L'utilisateur";
        });
        $this->assertNotNull($utilToken, "Le n-gramme 'util' de 'L'utilisateur' devrait être indexé");
        $this->assertEquals("L'utilisateur", $utilToken->original_text);

        // Vérifier qu'un n-gramme de 'Jean' existe (taille 4)
        $jeanToken = $descTokens->first(function ($token) {
            return $token->token === 'jean' && $token->original_text === 'Jean';
        });
        $this->assertNotNull($jeanToken, "Le n-gramme 'jean' de 'Jean' devrait être indexé");
        $this->assertEquals('Jean', $jeanToken->original_text);

        // Vérifier qu'un n-gramme de 'Pierre' existe (taille 4)
        $pierreToken = $descTokens->first(function ($token) {
            return $token->token === 'pier' && $token->original_text === 'Pierre';
        });
        $this->assertNotNull($pierreToken, "Le n-gramme 'pier' de 'Pierre' devrait être indexé");
        $this->assertEquals('Pierre', $pierreToken->original_text);

        // Vérifier qu'un n-gramme de 'acheté' existe
        $acheteToken = $descTokens->first(function ($token) {
            return $token->token === 'ache' && $token->original_text === 'acheté';
        });
        $this->assertNotNull($acheteToken, "Le n-gramme 'ache' de 'acheté' devrait être indexé");
        $this->assertEquals('acheté', $acheteToken->original_text);

        // Vérifier qu'un n-gramme de 'produits' existe
        $produitToken = $descTokens->first(function ($token) {
            return $token->token === 'produ' && $token->original_text === 'produits';
        });
        $this->assertNotNull($produitToken, "Le n-gramme 'produ' de 'produits' devrait être indexé");
        $this->assertEquals('produits', $produitToken->original_text);
    }
}
