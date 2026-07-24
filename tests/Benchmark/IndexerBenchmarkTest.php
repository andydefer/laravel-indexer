<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Benchmark;

use AndyDefer\DomainStructures\Normalizers\Core\NormalizerInterface;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\LaravelIndexer\Tests\Benchmark\Factories\TestDataFactory;
use AndyDefer\LaravelIndexer\Tests\Benchmark\TestCase\MysqlBenchmarkTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class IndexerBenchmarkTest extends MysqlBenchmarkTestCase
{
    use RefreshDatabase;

    private IndexWriter $writer;

    private TestDataFactory $factory;

    private IndexedDocumentRepositoryInterface $documentRepository;

    private IndexedTokenRepositoryInterface $tokenRepository;

    private IndexerConfigInterface $config;

    private TextNormalizerInterface $textNormalizer;

    private NGramGeneratorInterface $ngramGenerator;

    private NormalizerInterface $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentRepository = $this->app->make(IndexedDocumentRepositoryInterface::class);
        $this->tokenRepository = $this->app->make(IndexedTokenRepositoryInterface::class);
        $this->config = $this->app->make(IndexerConfigInterface::class);
        $this->normalizer = $this->app->make(NormalizerInterface::class);
        $this->textNormalizer = $this->app->make(TextNormalizerInterface::class);
        $this->ngramGenerator = $this->app->make(NGramGeneratorInterface::class);
        $this->writer = $this->app->make(IndexWriter::class);
        $this->factory = new TestDataFactory;
    }

    /**
     * @benchmark
     */
    public function test_benchmark_indexing_100(): void
    {
        $size = 100;
        $collection = $this->factory->createCollection($size);

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $this->writer->indexMany($collection);

        $endMemory = memory_get_usage();
        $duration = microtime(true) - $startTime;

        $documentsCount = $this->documentRepository->getModel()->newQuery()->count();
        $tokensCount = $this->tokenRepository->getModel()->newQuery()->count();

        // ==================== RECHERCHE ====================
        $query = new SearchQueryRecord(
            query: new SearchQueryVO('agn=address')
        );

        $indexer = app(IndexerService::class);

        $searchStart = microtime(true);
        $results = $indexer->search($query);
        $searchDuration = microtime(true) - $searchStart;

        // ==================== AFFICHAGE ====================
        $this->addToAssertionCount(1);

        echo sprintf(
            "\n📊 %d documents indexés en %.4f secondes",
            $size,
            $duration
        );
        echo sprintf(
            "\n   📁 Documents en base: %d | 🔤 Tokens: %d",
            $documentsCount,
            $tokensCount
        );
        echo sprintf(
            "\n   💾 Mémoire utilisée: %.2f MB",
            ($endMemory - $startMemory) / 1024 / 1024
        );
        echo sprintf(
            "\n🔍 Recherche 'agn=address' en %.4f secondes",
            $searchDuration
        );
        echo sprintf(
            "\n   📊 Résultats: %d\n",
            $results->count()
        );
    }

    /**
     * @benchmark
     */
    public function test_benchmark_search(): void
    {
        $collection = $this->factory->createCollection(5);
        $this->writer->indexMany($collection);

        $query = 'john';
        $startTime = microtime(true);

        $tokens = $this->tokenRepository->getModel()->newQuery()
            ->where('token', 'LIKE', $query.'%')
            ->limit(10)
            ->get();

        $duration = microtime(true) - $startTime;

        $this->addToAssertionCount(1);

        echo sprintf(
            "\n🔍 Recherche '%s' en %.4f secondes",
            $query,
            $duration
        );
        echo sprintf(
            "\n   📊 Résultats: %d\n",
            $tokens->count()
        );
    }

    /**
     * @benchmark
     */
    public function test_benchmark_search_with_cluster(): void
    {
        $collection = $this->factory->createCollection(5);
        $this->writer->indexMany($collection);

        $query = 'john';
        $cluster = 'tenant:company';

        $startTime = microtime(true);

        $tokens = $this->tokenRepository->getModel()->newQuery()
            ->where('token', 'LIKE', $query.'%')
            ->whereHas('document', function ($q) use ($cluster) {
                $q->where('cluster', 'LIKE', '%'.$cluster.'%');
            })
            ->limit(10)
            ->get();

        $duration = microtime(true) - $startTime;

        $this->addToAssertionCount(1);

        echo sprintf(
            "\n🔍 Recherche '%s' avec cluster '%s' en %.4f secondes",
            $query,
            $cluster,
            $duration
        );
        echo sprintf(
            "\n   📊 Résultats: %d\n",
            $tokens->count()
        );
    }
}
