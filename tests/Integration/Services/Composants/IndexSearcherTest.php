<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services\Composants;

use AndyDefer\DomainStructures\Normalizers\Core\NormalizerInterface;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Services\Composants\IndexSearcher;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;

final class IndexSearcherTest extends IntegrationTestCase
{
    private IndexWriter $indexWriter;

    private IndexSearcher $indexSearcher;

    private IndexedDocumentRepository $documentRepository;

    private IndexedTokenRepository $tokenRepository;

    private IndexerConfig $config;

    private NormalizerInterface $normalizer;

    private TextNormalizerInterface $textNormalizer;

    private NGramGeneratorInterface $ngramGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        // Config pour les tests : min_size=3, max_size=5
        $this->app['config']->set('indexer.token_types.ngrams.min_size', 3);
        $this->app['config']->set('indexer.token_types.ngrams.max_size', 5);

        // Re-bind IndexerConfig après changement de config
        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        $this->indexWriter = $this->app->make(IndexWriter::class);
        $this->indexSearcher = $this->app->make(IndexSearcher::class);
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
        $record = new IndexedDocumentRecord(
            fingerprint: $fingerPrint,
            data: StrictAssociative::from($data),
            cluster: $clusterVO,
        );

        $this->indexWriter->index($record);
    }

    // ==================== TESTS EXISTS ====================

    public function test_exists_returns_true_when_document_exists(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', ['name' => 'John Doe']);

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
        $exists = $this->indexSearcher->exists($fingerPrint);

        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_document_not_exists(): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|999');
        $exists = $this->indexSearcher->exists($fingerPrint);

        $this->assertFalse($exists);
    }

    // ==================== TESTS SEARCH SIMPLE ====================

    public function test_search_simple_returns_results(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('App.Models.User|123', $result->item->fingerprint->getValue());
        $this->assertEquals('name', $result->field);
        $this->assertEquals('john', $result->gram_value);
        $this->assertEquals(GramType::LEXICAL, $result->gram_type);
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('xyz=name')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(0, $results);
    }

    // ==================== TESTS SEARCH WITH MULTIPLE FIELDS ====================

    public function test_search_with_multiple_fields_returns_results(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
            'description' => 'Software Developer',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name,description')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('name', $result->field);
        $this->assertEquals('john', $result->gram_value);
    }

    // ==================== TESTS SEARCH WITH MULTIPLE NGRAMS ====================

    public function test_search_with_multiple_ngrams_returns_intersection(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
            'description' => 'Senior Developer',
        ]);

        $this->createAndIndexDocument('App.Models.User|456', [
            'name' => 'Pierre Smith',
            'description' => 'Junior Developer',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name|developer=description')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('App.Models.User|123', $result->item->fingerprint->getValue());
    }

    // ==================== TESTS SEARCH WITH FINGERPRINT FILTER ====================

    public function test_search_with_fingerprint_filter_returns_only_matching_document(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', ['name' => 'John Doe']);
        $this->createAndIndexDocument('App.Models.User|456', ['name' => 'John Smith']);

        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name'),
            fingerprint: $fingerPrint,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('App.Models.User|123', $result->item->fingerprint->getValue());
    }

    // ==================== TESTS SEARCH WITH CLUSTER FILTER ====================

    public function test_search_with_cluster_filter_returns_only_matching_documents(): void
    {
        $this->createAndIndexDocument(
            'App.Models.User|123',
            ['name' => 'John Doe'],
            'model:User|tenant:company_abc|env:production'
        );

        $this->createAndIndexDocument(
            'App.Models.User|456',
            ['name' => 'John Smith'],
            'model:User|tenant:company_xyz|env:staging'
        );

        $cluster = new ClusterVO('tenant:company_abc|env:production');
        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name'),
            cluster: $cluster,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('App.Models.User|123', $result->item->fingerprint->getValue());
    }

    // ==================== TESTS SEARCH WITH LIMIT ====================

    public function test_search_with_limit_returns_only_limit_results(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createAndIndexDocument(
                'App.Models.User|'.$i,
                ['name' => 'John '.$i]
            );
        }

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name'),
            limit: 3,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(3, $results);
    }

    // ==================== TESTS SEARCH WITH METAPHONE ====================

    public function test_search_uses_metaphone_when_lexical_no_match(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('jon=name')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('App.Models.User|123', $result->item->fingerprint->getValue());
        $this->assertEquals('jon', $result->gram_value);
    }

    // ==================== TESTS SEARCH WITH NESTED DATA ====================

    public function test_search_with_nested_data_returns_results(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
            'profile' => [
                'bio' => 'Software Developer',
                'social' => [
                    'twitter' => '@johndoe',
                ],
            ],
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=profile.social.twitter')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('profile.social.twitter', $result->field);
        $this->assertEquals('john', $result->gram_value);
    }

    // ==================== TESTS SEARCH WITH ARRAY VALUES ====================

    public function test_search_with_array_values_returns_results(): void
    {
        $this->createAndIndexDocument('App.Models.Product|123', [
            'name' => 'Laptop Pro',
            'tags' => ['php', 'laravel', 'vuejs'],
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('php=tags')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('tags', $result->field);
        $this->assertEquals('php', $result->gram_value);
    }

    // ==================== TESTS SEARCH WITH PARTIAL MATCH ====================

    public function test_search_with_partial_match_returns_results(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('joh=name')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('App.Models.User|123', $result->item->fingerprint->getValue());
        $this->assertEquals('joh', $result->gram_value);
    }

    // ==================== TESTS SEARCH CASE INSENSITIVE ====================

    public function test_search_is_case_insensitive(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('JOHN=name')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('App.Models.User|123', $result->item->fingerprint->getValue());
        $this->assertEquals('JOHN', $result->gram_value);
    }

    // ==================== TESTS SEARCH WITH SPECIAL CHARACTERS ====================

    public function test_search_with_special_characters_returns_results(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'Jean-Pierre',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('jean=name')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    // ==================== TESTS POUR min_size ET max_size ====================

    public function test_search_with_custom_min_size_clamped_to_config(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
        ]);

        // min_size=2 est clampé à 3 (config)
        // On cherche un terme qui n'existe pas
        $query = new SearchQueryRecord(
            query: new SearchQueryVO('xyz=name'),
            min_size: 2,
            max_size: 3,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(0, $results);
    }

    public function test_search_with_custom_max_size_clamped_to_config(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'Programming',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('programming=name'),
            min_size: 3,
            max_size: 6,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_search_without_min_max_uses_config_defaults(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name')
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_search_with_max_size_lower_than_min_size_uses_config(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name'),
            min_size: 5,
            max_size: 3,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_search_with_min_size_equal_to_max_size(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'Programming',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('programming=name'),
            min_size: 4,
            max_size: 4,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_search_with_min_size_equals_term_length(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name'),
            min_size: 4,
            max_size: 4,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_search_with_min_size_greater_than_config_uses_config(): void
    {
        $this->createAndIndexDocument('App.Models.User|123', [
            'name' => 'John Doe',
        ]);

        $query = new SearchQueryRecord(
            query: new SearchQueryVO('john=name'),
            min_size: 8,
            max_size: 10,
        );

        $results = $this->indexSearcher->search($query);

        $this->assertInstanceOf(IndexableSearchResultCollection::class, $results);
        $this->assertCount(1, $results);
    }
}
