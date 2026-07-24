<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Tasks\Recurring;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexedTokenRepositoryInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Directives\GenericIndexModelsDirective;
use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Services\GenericIndexerService;
use AndyDefer\LaravelIndexer\Tasks\RecurringTasks\GenericOrchestratorRecurringTask;
use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestPharmacy;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestProduct;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class GenericOrchestratorRecurringTaskTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private const TEST_BATCH_SIZE = 10;

    private DirectiveTestingService $service;

    private RecurringTaskServiceInterface $recurringTaskService;

    private UniqueTaskServiceInterface $uniqueTaskService;

    private IndexedDocumentRepositoryInterface $documentRepository;

    private static int $doctorCounter = 0;

    private static int $pharmacyCounter = 0;

    private static int $productCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        // Config pour les tests
        $this->app['config']->set('indexer.batch_size', self::TEST_BATCH_SIZE);
        $this->app['config']->set('indexer.token_types.ngrams.min_size', 2);
        $this->app['config']->set('indexer.token_types.ngrams.max_size', 4);
        $this->app['config']->set('indexer.model_indexables', [
            TestDoctor::class => 'type:doctor|status:active',
            TestPharmacy::class => 'type:pharmacy|status:active',
            TestProduct::class => 'type:product|status:published',
        ]);

        // Re-bind IndexerConfig après changement de config
        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        // Re-bind IndexWriter avec la nouvelle config
        $this->app->singleton(IndexWriter::class, function ($app) {
            return new IndexWriter(
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                tokenRepository: $app->make(IndexedTokenRepositoryInterface::class),
                textNormalizer: $app->make(TextNormalizerInterface::class),
                ngramGenerator: $app->make(NGramGeneratorInterface::class),
                config: $app->make(IndexerConfigInterface::class),
            );
        });

        // Re-bind GenericIndexerService avec la nouvelle config
        $this->app->singleton(GenericIndexerInterface::class, function ($app) {
            return new GenericIndexerService(
                indexer: $app->make(IndexerInterface::class),
                documentRepository: $app->make(IndexedDocumentRepositoryInterface::class),
                config: $app->make(IndexerConfigInterface::class),
            );
        });

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirectives([GenericIndexModelsDirective::class, TasksProcessDirective::class]);

        $this->recurringTaskService = $this->app->make(RecurringTaskServiceInterface::class);
        $this->uniqueTaskService = $this->app->make(UniqueTaskServiceInterface::class);
        $this->documentRepository = $this->app->make(IndexedDocumentRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        $this->service->destroy();
        parent::tearDown();
    }

    private function createDoctor(array $attributes = []): TestDoctor
    {
        self::$doctorCounter++;

        return TestDoctor::create(array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'specialty' => 'Cardiology',
            'email' => 'john_'.self::$doctorCounter.'@hospital.com',
            'phone' => '123456789',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'hospital' => 'General Hospital',
            'is_active' => true,
        ], $attributes));
    }

    private function createPharmacy(array $attributes = []): TestPharmacy
    {
        self::$pharmacyCounter++;

        return TestPharmacy::create(array_merge([
            'name' => 'Central Pharmacy '.self::$pharmacyCounter,
            'address' => '456 Oak Ave',
            'city' => 'New York',
            'postal_code' => '10001',
            'phone' => '987654321',
            'email' => 'contact_'.self::$pharmacyCounter.'@centralpharmacy.com',
            'is_active' => true,
        ], $attributes));
    }

    private function createProduct(array $attributes = []): TestProduct
    {
        self::$productCounter++;

        return TestProduct::create(array_merge([
            'name' => 'Product '.self::$productCounter,
            'reference' => 'REF-'.str_pad((string) self::$productCounter, 3, '0', STR_PAD_LEFT),
            'description' => 'Test product description '.self::$productCounter,
            'is_published' => true,
        ], $attributes));
    }

    private function registerOrchestratorTask(): void
    {
        $startAt = Carbon::now()->subSeconds(5)->toIso8601String();

        $config = RecurringTaskConfigRecord::from([
            'interval_seconds' => new DurationVO(60),
            'start_at' => new Iso8601DateTimeVO($startAt),
            'max_attempts' => new MaxFailedAttemptsVO(3),
            'description' => new DescriptionVO('Generic orchestrator task for indexing models'),
        ]);

        $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(GenericOrchestratorRecurringTask::class),
            StrictDataObject::from(['enabled' => true]),
            $config
        );
    }

    public function test_orchestrator_creates_batch_tasks_for_all_models(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createDoctor();
        }

        for ($i = 0; $i < 10; $i++) {
            $this->createPharmacy();
        }

        for ($i = 0; $i < 5; $i++) {
            $this->createProduct();
        }

        $this->registerOrchestratorTask();

        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $batchTasks = $this->uniqueTaskService->findPending();

        $batchTasksByFqcn = [];
        foreach ($batchTasks as $task) {
            $taskData = $task->toArray();
            if (isset($taskData['fqcn']) && $taskData['fqcn'] === GenericIndexBatchUniqueTask::class) {
                $batchTasksByFqcn[] = $task;
            }
        }

        $expectedBatches = 2 + 1 + 1;
        $this->assertCount($expectedBatches, $batchTasksByFqcn);

        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $response = $this->service->run('tasks:process');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $doctorCount = $this->documentRepository->countByNamespace(TestDoctor::class);
        $pharmacyCount = $this->documentRepository->countByNamespace(TestPharmacy::class);
        $productCount = $this->documentRepository->countByNamespace(TestProduct::class);

        $this->assertEquals(15, $doctorCount);
        $this->assertEquals(10, $pharmacyCount);
        $this->assertEquals(5, $productCount);
    }

    public function test_orchestrator_creates_no_tasks_when_no_models(): void
    {
        $this->app['config']->set('indexer.model_indexables', []);

        // Re-bind IndexerConfig après changement de config
        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        $this->registerOrchestratorTask();

        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $batchTasks = $this->uniqueTaskService->findPending();
        $this->assertEquals(0, $batchTasks->count());
    }

    public function test_orchestrator_only_processes_active_models(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createDoctor(['is_active' => true]);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->createDoctor(['is_active' => false]);
        }

        $this->app['config']->set('indexer.model_indexables', [
            TestDoctor::class => 'type:doctor|status:active',
        ]);

        // Re-bind IndexerConfig après changement de config
        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        $this->registerOrchestratorTask();

        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $batchTasks = $this->uniqueTaskService->findPending();

        $batchTasksByFqcn = [];
        foreach ($batchTasks as $task) {
            $taskData = $task->toArray();
            if (isset($taskData['fqcn']) && $taskData['fqcn'] === GenericIndexBatchUniqueTask::class) {
                $batchTasksByFqcn[] = $task;
            }
        }

        $this->assertCount(1, $batchTasksByFqcn);

        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $response = $this->service->run('tasks:process');
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $count = $this->documentRepository->countByNamespace(TestDoctor::class);
        $this->assertEquals(10, $count);
    }

    public function test_orchestrator_creates_correct_number_of_batches(): void
    {
        $totalUsers = self::TEST_BATCH_SIZE * 3;

        for ($i = 0; $i < $totalUsers; $i++) {
            $this->createDoctor();
        }

        $this->app['config']->set('indexer.model_indexables', [
            TestDoctor::class => 'type:doctor|status:active',
        ]);

        // Re-bind IndexerConfig après changement de config
        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        $this->registerOrchestratorTask();

        Carbon::setTestNow(Carbon::now()->addSeconds(10));

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $batchTasks = $this->uniqueTaskService->findPending();

        $batchTasksByFqcn = [];
        foreach ($batchTasks as $task) {
            $taskData = $task->toArray();
            if (isset($taskData['fqcn']) && $taskData['fqcn'] === GenericIndexBatchUniqueTask::class) {
                $batchTasksByFqcn[] = $task;
            }
        }

        $expectedBatches = (int) ceil($totalUsers / self::TEST_BATCH_SIZE);
        $this->assertCount($expectedBatches, $batchTasksByFqcn);
    }
}
