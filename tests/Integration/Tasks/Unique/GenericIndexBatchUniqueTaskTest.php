<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Tasks\Unique;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface;
use AndyDefer\LaravelIndexer\Directives\GenericIndexModelsDirective;
use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class GenericIndexBatchUniqueTaskTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private DirectiveTestingService $service;

    private UniqueTaskServiceInterface $uniqueTaskService;

    private IndexedDocumentRepositoryInterface $documentRepository;

    private static int $doctorCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        // Config pour les tests
        $this->app['config']->set('indexer.batch_size', 10);
        $this->app['config']->set('indexer.token_types.ngrams.min_size', 3);
        $this->app['config']->set('indexer.token_types.ngrams.max_size', 5);

        // Re-bind IndexerConfig après changement de config
        $this->app->singleton(IndexerConfigInterface::class, function ($app) {
            return new IndexerConfig($app['config']);
        });

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirectives([GenericIndexModelsDirective::class, TasksProcessDirective::class]);

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

    private function registerBatchTask(IndexableVO $indexableVO, array $ids): void
    {
        $scheduledAt = Carbon::now()->subSeconds(5)->toIso8601String();

        $config = UniqueTaskConfigRecord::from([
            'scheduled_at' => new Iso8601DateTimeVO($scheduledAt),
            'max_attempts' => new MaxFailedAttemptsVO(3),
            'grace_period' => new DurationVO(3600),
            'description' => new DescriptionVO('Batch task for indexing'),
        ]);

        $payload = StrictDataObject::from([
            'indexable' => $indexableVO,
            'ids' => $ids,
        ]);

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
            $payload,
            $config
        );

    }

    public function test_batch_task_indexes_doctors_successfully(): void
    {
        $doctors = [];
        for ($i = 0; $i < 5; $i++) {
            $doctors[] = $this->createDoctor();
        }

        $ids = array_map(fn ($d) => $d->id, $doctors);
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);

        $this->registerBatchTask($indexableVO, $ids);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $count = $this->documentRepository->countByNamespace(TestDoctor::class);
        $this->assertEquals(5, $count);
    }

    public function test_batch_task_skips_already_indexed_items(): void
    {
        $doctor1 = $this->createDoctor();
        $doctor2 = $this->createDoctor();
        $doctor3 = $this->createDoctor();

        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);

        $this->registerBatchTask($indexableVO, [$doctor1->id, $doctor2->id, $doctor3->id]);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $count = $this->documentRepository->countByNamespace(TestDoctor::class);
        $this->assertEquals(3, $count);
    }

    public function test_batch_task_skips_inactive_items(): void
    {
        $activeDoctor = $this->createDoctor(['is_active' => true]);
        $inactiveDoctor = $this->createDoctor(['is_active' => false]);

        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);
        $this->registerBatchTask($indexableVO, [$activeDoctor->id, $inactiveDoctor->id]);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $count = $this->documentRepository->countByNamespace(TestDoctor::class);
        $this->assertEquals(1, $count);
    }

    public function test_batch_task_with_empty_ids(): void
    {
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);
        $this->registerBatchTask($indexableVO, []);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $count = $this->documentRepository->countByNamespace(TestDoctor::class);
        $this->assertEquals(0, $count);
    }

    public function test_batch_task_with_item_not_found(): void
    {
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(TestDoctor::class, $cluster);
        $this->registerBatchTask($indexableVO, [99999]);

        $response = $this->service->run('tasks:process');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $count = $this->documentRepository->countByNamespace(TestDoctor::class);
        $this->assertEquals(0, $count);
    }
}
