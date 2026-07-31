<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services\Composants;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;

final class IndexWriterTest extends IntegrationTestCase
{
    private IndexWriter $indexWriter;

    private IndexedDocumentRepository $documentRepository;

    private IndexedTokenRepository $tokenRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Config pour les tests : min_size=2, max_size=4
        $this->app['config']->set('indexer.token_types.ngrams.min_size', 2);
        $this->app['config']->set('indexer.token_types.ngrams.max_size', 4);

        // Re-bind IndexerConfig après changement de config
        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        $this->indexWriter = $this->app->make(IndexWriter::class);
        $this->documentRepository = $this->app->make(IndexedDocumentRepository::class);
        $this->tokenRepository = $this->app->make(IndexedTokenRepository::class);
    }

    // ==================== HELPER ====================

    private function createClusterVO(array $cluster): ClusterVO
    {
        return new ClusterVO($cluster);
    }

    // ==================== TESTS ====================

    public function test_index_creates_document_and_tokens(): void
    {
        $fingerprint = new IndexableFingerprintVO('App\Models\User|123');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $this->assertNotNull($document);
        $this->assertEquals('App\Models\User|123', $document->fingerprint->getValue());

        $tokens = $this->tokenRepository->findByDocumentId($document->id);
        $this->assertNotEmpty($tokens);

        $johnToken = $tokens->first(function ($token) {
            return $token->token === 'john' && $token->field === 'name';
        });
        $this->assertNotNull($johnToken);
        $this->assertEquals('John', $johnToken->original_text);
        $this->assertEquals(1, $johnToken->frequency);

        // Avec min_size=2, 'jo' devrait exister
        $joToken = $tokens->first(function ($token) {
            return $token->token === 'jo' && $token->field === 'name';
        });
        $this->assertNotNull($joToken);
    }

    public function test_index_increments_frequency_on_existing_token(): void
    {
        $fingerPrint1 = new IndexableFingerprintVO('App\Models\User|456');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'name' => 'John Doe',
        ]);

        $record1 = new IndexedDocumentRecord(
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

        $fingerPrint2 = new IndexableFingerprintVO('App\Models\User|789');
        $record2 = new IndexedDocumentRecord(
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
        $fingerprint = new IndexableFingerprintVO('App\Models\User|789');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
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

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $fields = $tokens->pluck('field')->unique()->toArray();
        $this->assertContains('name', $fields);
        $this->assertContains('profile.bio', $fields);
        $this->assertContains('profile.social.twitter', $fields);
        $this->assertContains('profile.social.github', $fields);

        // Avec min_size=2, 'so' devrait exister
        $soToken = $tokens->first(function ($token) {
            return $token->field === 'profile.bio' && $token->token === 'so';
        });
        $this->assertNotNull($soToken);

        $sofToken = $tokens->first(function ($token) {
            return $token->field === 'profile.bio' && $token->token === 'sof';
        });
        $this->assertNotNull($sofToken);
        $this->assertEquals('Software', $sofToken->original_text);
    }

    public function test_index_handles_array_values(): void
    {
        $fingerprint = new IndexableFingerprintVO('App\Models\Product|123');
        $cluster = $this->createClusterVO([
            'model' => 'Product',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'name' => 'Laptop Pro',
            'tags' => ['php', 'laravel', 'vuejs'],
            'specs' => [
                'ram' => '16GB',
                'storage' => '512GB',
            ],
        ]);

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $tagTokens = $tokens->filter(function ($token) {
            return $token->field === 'tags';
        });

        $phpExists = $tagTokens->first(function ($token) {
            return str_contains($token->original_text, 'php');
        });
        $this->assertNotNull($phpExists, 'php non trouvé dans les tags');

        $laravelExists = $tagTokens->first(function ($token) {
            return str_contains($token->original_text, 'laravel');
        });
        $this->assertNotNull($laravelExists, 'laravel non trouvé dans les tags');

        $vuejsExists = $tagTokens->first(function ($token) {
            return str_contains($token->original_text, 'vuejs');
        });
        $this->assertNotNull($vuejsExists, 'vuejs non trouvé dans les tags');

        // Vérifier les tokens des specs
        $specTokens = $tokens->filter(function ($token) {
            return $token->field === 'specs.ram' || $token->field === 'specs.storage';
        });
        $this->assertNotEmpty($specTokens);
    }

    public function test_index_converts_numeric_and_boolean_to_string(): void
    {
        $fingerprint = new IndexableFingerprintVO('App\Models\User|999');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'age' => 30,
            'active' => true,
            'score' => 99.99,
        ]);

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $fields = $tokens->pluck('field')->unique()->toArray();

        // ✅ name doit être indexé
        $this->assertContains('name', $fields);

        // ✅ Les valeurs numériques sont converties en string et indexées
        $this->assertContains('age', $fields);
        $this->assertContains('score', $fields);

        // ❌ Les booléens ne sont PAS convertis en string
        $this->assertNotContains('active', $fields);

        // ✅ Vérifier que les tokens de name existent
        $johnToken = $tokens->first(function ($token) {
            return $token->field === 'name' && $token->token === 'john';
        });
        $this->assertNotNull($johnToken);

        // ✅ Vérifier que les tokens de age existent
        $ageToken = $tokens->first(function ($token) {
            return $token->field === 'age' && $token->token === '30';
        });
        $this->assertNotNull($ageToken);

        // ✅ Vérifier que les tokens de score existent
        $scoreToken = $tokens->first(function ($token) {
            return $token->field === 'score' && $token->token === '99';
        });
        $this->assertNotNull($scoreToken);
    }

    public function test_index_uses_config_ngram_sizes(): void
    {
        // Config déjà settée dans setUp avec min_size=2, max_size=4

        $fingerprint = new IndexableFingerprintVO('App\Models\User|111');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'name' => 'John',
        ]);

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $lexicalTokens = $tokens->filter(function ($token) {
            return $token->token_type === GramType::LEXICAL && $token->field === 'name';
        });

        $tokensList = $lexicalTokens->pluck('token')->toArray();

        // min_size=2 donc 'jo' et 'oh' doivent exister
        $this->assertContains('jo', $tokensList);
        $this->assertContains('oh', $tokensList);
        $this->assertContains('hn', $tokensList);

        // max_size=4 donc 'john' doit exister
        $this->assertContains('john', $tokensList);

        // Pas de token de taille 1
        $this->assertNotContains('j', $tokensList);
        $this->assertNotContains('o', $tokensList);
        $this->assertNotContains('h', $tokensList);
        $this->assertNotContains('n', $tokensList);
    }

    public function test_index_many_handles_multiple_records(): void
    {
        $records = new IndexableRecordCollection;

        $record1 = new IndexedDocumentRecord(
            fingerprint: new IndexableFingerprintVO('App\Models\User|1'),
            data: StrictAssociative::from(['name' => 'User 1']),
            cluster: $this->createClusterVO(['model' => 'User', 'tenant' => 'company_abc']),
        );
        $records->add($record1);

        $record2 = new IndexedDocumentRecord(
            fingerprint: new IndexableFingerprintVO('App\Models\User|2'),
            data: StrictAssociative::from(['name' => 'User 2']),
            cluster: $this->createClusterVO(['model' => 'User', 'tenant' => 'company_abc']),
        );
        $records->add($record2);

        $this->indexWriter->indexMany($records);

        $doc1 = $this->documentRepository->findByFingerPrint(new IndexableFingerprintVO('App\Models\User|1'));
        $doc2 = $this->documentRepository->findByFingerPrint(new IndexableFingerprintVO('App\Models\User|2'));

        $this->assertNotNull($doc1);
        $this->assertNotNull($doc2);

        $tokens1 = $this->tokenRepository->findByDocumentId($doc1->id);
        $tokens2 = $this->tokenRepository->findByDocumentId($doc2->id);

        $this->assertNotEmpty($tokens1);
        $this->assertNotEmpty($tokens2);
    }

    // ==================== TESTS POUR LE CHUNKING ====================

    public function test_index_handles_long_text_with_chunking(): void
    {
        $longText = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.';

        $fingerprint = new IndexableFingerprintVO('App\Models\User|999');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'description' => $longText,
        ]);

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $descriptionTokens = $tokens->filter(function ($token) {
            return $token->field === 'description';
        });

        $this->assertNotEmpty($descriptionTokens);

        // Vérifier les tokens avec les bonnes tailles (min_size=2)
        $loremToken = $descriptionTokens->first(function ($token) {
            return $token->token === 'lo' && $token->field === 'description';
        });
        $this->assertNotNull($loremToken, 'Token "lo" de "Lorem" devrait être indexé');
        $this->assertEquals('Lorem', $loremToken->original_text);

        $ipsumToken = $descriptionTokens->first(function ($token) {
            return $token->token === 'ip' && $token->field === 'description';
        });
        $this->assertNotNull($ipsumToken, 'Token "ip" de "ipsum" devrait être indexé');
        $this->assertEquals('ipsum', $ipsumToken->original_text);

        // Vérifier qu'il y a des tokens de taille 4
        $loreToken = $descriptionTokens->first(function ($token) {
            return $token->token === 'lore' && $token->field === 'description';
        });
        $this->assertNotNull($loreToken, 'Token "lore" de "Lorem" devrait être indexé');
    }

    public function test_index_handles_very_long_single_word(): void
    {
        $longWord = 'Supercalifragilisticexpialidocious';

        $fingerprint = new IndexableFingerprintVO('App\Models\User|888');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'name' => $longWord,
        ]);

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $nameTokens = $tokens->filter(function ($token) {
            return $token->field === 'name';
        });

        $this->assertNotEmpty($nameTokens);

        // Vérifier qu'un n-gramme de taille 2 existe
        $suToken = $nameTokens->first(function ($token) {
            return $token->token === 'su' && $token->field === 'name';
        });
        $this->assertNotNull($suToken, 'Des n-grammes de "Supercalifragilisticexpialidocious" devraient être indexés');

        // Vérifier qu'un n-gramme de taille 4 existe
        $supeToken = $nameTokens->first(function ($token) {
            return $token->token === 'supe' && $token->field === 'name';
        });
        $this->assertNotNull($supeToken, 'Des n-grammes de taille 4 devraient être indexés');
    }

    public function test_index_handles_mixed_short_and_long_texts(): void
    {
        $fingerprint = new IndexableFingerprintVO('App\Models\User|777');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'short' => 'Hello World',
            'medium' => 'This is a medium text',
            'long' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        ]);

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $fields = $tokens->pluck('field')->unique()->toArray();
        $this->assertContains('short', $fields);
        $this->assertContains('medium', $fields);
        $this->assertContains('long', $fields);

        $shortTokens = $tokens->filter(function ($token) {
            return $token->field === 'short';
        });
        $heToken = $shortTokens->first(function ($token) {
            return $token->token === 'he' && $token->field === 'short';
        });
        $this->assertNotNull($heToken);
        $this->assertEquals('Hello', $heToken->original_text);

        $longTokens = $tokens->filter(function ($token) {
            return $token->field === 'long';
        });
        $loToken = $longTokens->first(function ($token) {
            return $token->token === 'lo' && $token->field === 'long';
        });
        $this->assertNotNull($loToken);
        $this->assertEquals('Lorem', $loToken->original_text);
    }

    public function test_index_handles_text_with_special_characters(): void
    {
        $textWithSpecialChars = "L'utilisateur Jean-Pierre a acheté 2 produits à 100€ !";

        $fingerprint = new IndexableFingerprintVO('App\Models\User|666');
        $cluster = $this->createClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $data = StrictAssociative::from([
            'description' => $textWithSpecialChars,
        ]);

        $record = new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: $cluster,
        );

        $this->indexWriter->index($record);

        $document = $this->documentRepository->findByFingerPrint($fingerprint);
        $tokens = $this->tokenRepository->findByDocumentId($document->id);

        $descTokens = $tokens->filter(function ($token) {
            return $token->field === 'description';
        });

        $this->assertNotEmpty($descTokens);

        // Vérifier les tokens de taille 2
        $utToken = $descTokens->first(function ($token) {
            return $token->token === 'ut' && $token->field === 'description';
        });
        $this->assertNotNull($utToken, "Le n-gramme 'ut' de 'L'utilisateur' devrait être indexé");
        $this->assertEquals("L'utilisateur", $utToken->original_text);

        $jeToken = $descTokens->first(function ($token) {
            return $token->token === 'je' && $token->field === 'description';
        });
        $this->assertNotNull($jeToken, "Le n-gramme 'je' de 'Jean' devrait être indexé");
        $this->assertEquals('Jean', $jeToken->original_text);

        $piToken = $descTokens->first(function ($token) {
            return $token->token === 'pi' && $token->field === 'description';
        });
        $this->assertNotNull($piToken, "Le n-gramme 'pi' de 'Pierre' devrait être indexé");
        $this->assertEquals('Pierre', $piToken->original_text);

        $acToken = $descTokens->first(function ($token) {
            return $token->token === 'ac' && $token->field === 'description';
        });
        $this->assertNotNull($acToken, "Le n-gramme 'ac' de 'acheté' devrait être indexé");
        $this->assertEquals('acheté', $acToken->original_text);

        // Vérifier les tokens de 'produits' (taille 2)
        $prToken = $descTokens->first(function ($token) {
            return $token->token === 'pr' && $token->original_text === 'produits' && $token->field === 'description';
        });
        $this->assertNotNull($prToken, "Le n-gramme 'pr' de 'produits' devrait être indexé");
        $this->assertEquals('produits', $prToken->original_text);
    }
}
