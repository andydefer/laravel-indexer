<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Services\Composants;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Indexable\TestIndexableEntity;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Indexable\TestIndexableEntityNotIndexable;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

final class IndexableRecordFactoryTest extends IntegrationTestCase
{
    // ==================== TESTS CONVERT ====================

    public function test_convert_returns_indexed_document_record_with_cluster(): void
    {
        $entity = new TestIndexableEntity(
            key: '123',
            morphClass: 'App\Models\User',
            data: [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        );

        $cluster = new ClusterVO([
            'model' => 'User',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $record = IndexableRecordFactory::convert($entity, $cluster);

        $this->assertInstanceOf(IndexedDocumentRecord::class, $record);
        $this->assertInstanceOf(IndexableFingerPrintVO::class, $record->fingerprint);
        $this->assertInstanceOf(StrictAssociative::class, $record->data);
        $this->assertNotNull($record->cluster);

        $this->assertEquals('App\Models\User|123', $record->fingerprint->getValue());
        $this->assertEquals('123', $record->fingerprint->getId());
        $this->assertEquals('App\Models\User', $record->fingerprint->getNamespace());
        $this->assertEquals('User', $record->cluster->get('model'));
        $this->assertEquals('company_abc', $record->cluster->get('tenant'));
        $this->assertEquals('production', $record->cluster->get('env'));
        $this->assertEquals(['name' => 'John Doe', 'email' => 'john@example.com'], $record->data->toArray());
    }

    public function test_convert_with_cluster(): void
    {
        $entity = new TestIndexableEntity(
            key: '456',
            morphClass: 'App\Models\Product',
            data: [
                'name' => 'Laptop',
                'price' => 999.99,
            ],
        );

        $cluster = new ClusterVO([
            'model' => 'Product',
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $record = IndexableRecordFactory::convert($entity, $cluster);

        $this->assertInstanceOf(IndexedDocumentRecord::class, $record);
        $this->assertEquals('App\Models\Product|456', $record->fingerprint->getValue());

        $this->assertNotNull($record->cluster);
        $this->assertEquals('Product', $record->cluster->get('model'));
        $this->assertEquals('company_abc', $record->cluster->get('tenant'));
        $this->assertEquals('production', $record->cluster->get('env'));
        $this->assertNull($record->cluster->get('nonexistent'));
    }

    public function test_convert_preserves_data_types(): void
    {
        $entity = new TestIndexableEntity(
            key: '999',
            morphClass: 'App\Models\Complex',
            data: [
                'name' => 'Complex Entity',
                'active' => true,
                'count' => 42,
                'price' => 99.99,
                'tags' => ['php', 'laravel', 'indexer'],
            ],
        );

        $cluster = new ClusterVO([
            'model' => 'Complex',
            'type' => 'test',
        ]);
        $record = IndexableRecordFactory::convert($entity, $cluster);

        $this->assertInstanceOf(IndexedDocumentRecord::class, $record);
        $this->assertEquals('App\Models\Complex|999', $record->fingerprint->getValue());

        $data = $record->data->toArray();
        $this->assertEquals('Complex Entity', $data['name']);
        $this->assertTrue($data['active']);
        $this->assertEquals(42, $data['count']);
        $this->assertEquals(99.99, $data['price']);
        $this->assertEquals(['php', 'laravel', 'indexer'], $data['tags']);
    }

    public function test_convert_with_should_not_be_indexed_returns_false(): void
    {
        $entity = new TestIndexableEntityNotIndexable(
            key: '123',
            morphClass: 'App\Models\Inactive',
            data: [
                'name' => 'Inactive Entity',
            ],
        );

        $cluster = new ClusterVO(['model' => 'Inactive']);
        $record = IndexableRecordFactory::convert($entity, $cluster);

        $this->assertInstanceOf(IndexedDocumentRecord::class, $record);
        $this->assertEquals('App\Models\Inactive|123', $record->fingerprint->getValue());
    }

    public function test_convert_multiple_entities_with_different_clusters(): void
    {
        $entities = [
            [
                'entity' => new TestIndexableEntity(
                    key: '1',
                    morphClass: 'App\Models\User',
                    data: ['name' => 'User 1', 'email' => 'user1@example.com'],
                ),
                'cluster' => new ClusterVO([
                    'model' => 'User',
                    'tenant' => 'company_a',
                ]),
            ],
            [
                'entity' => new TestIndexableEntity(
                    key: '2',
                    morphClass: 'App\Models\User',
                    data: ['name' => 'User 2', 'email' => 'user2@example.com'],
                ),
                'cluster' => new ClusterVO([
                    'model' => 'User',
                    'tenant' => 'company_b',
                ]),
            ],
            [
                'entity' => new TestIndexableEntity(
                    key: '3',
                    morphClass: 'App\Models\Product',
                    data: ['name' => 'Product 1', 'price' => 100.00],
                ),
                'cluster' => new ClusterVO([
                    'model' => 'Product',
                    'category' => 'electronics',
                ]),
            ],
        ];

        $records = [];
        foreach ($entities as $item) {
            $records[] = IndexableRecordFactory::convert($item['entity'], $item['cluster']);
        }

        $this->assertCount(3, $records);

        $this->assertEquals('App\Models\User|1', $records[0]->fingerprint->getValue());
        $this->assertEquals('App\Models\User|2', $records[1]->fingerprint->getValue());
        $this->assertEquals('App\Models\Product|3', $records[2]->fingerprint->getValue());

        $this->assertEquals('company_a', $records[0]->cluster->get('tenant'));
        $this->assertEquals('company_b', $records[1]->cluster->get('tenant'));
        $this->assertEquals('electronics', $records[2]->cluster->get('category'));
    }

    public function test_convert_handles_special_characters_in_cluster(): void
    {
        $entity = new TestIndexableEntity(
            key: '123',
            morphClass: 'App\Models\Special',
            data: [
                'name' => 'Special Entity',
            ],
        );

        $cluster = new ClusterVO([
            'key1' => 'value-with-dash',
            'key2' => 'value_with_underscore',
            'key3' => 'value.with.dots',
        ]);
        $record = IndexableRecordFactory::convert($entity, $cluster);

        $this->assertInstanceOf(IndexedDocumentRecord::class, $record);

        $this->assertNotNull($record->cluster);
        $this->assertEquals('value-with-dash', $record->cluster->get('key1'));
        $this->assertEquals('value_with_underscore', $record->cluster->get('key2'));
        $this->assertEquals('value.with.dots', $record->cluster->get('key3'));
        $this->assertNull($record->cluster->get('nonexistent'));
    }

    public function test_convert_same_entity_with_different_clusters(): void
    {
        $entity = new TestIndexableEntity(
            key: '123',
            morphClass: 'App\Models\User',
            data: [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        );

        $cluster1 = new ClusterVO([
            'tenant' => 'company_abc',
            'env' => 'production',
        ]);
        $cluster2 = new ClusterVO([
            'tenant' => 'company_xyz',
            'env' => 'staging',
        ]);

        $record1 = IndexableRecordFactory::convert($entity, $cluster1);
        $record2 = IndexableRecordFactory::convert($entity, $cluster2);

        $this->assertEquals('company_abc', $record1->cluster->get('tenant'));
        $this->assertEquals('company_xyz', $record2->cluster->get('tenant'));
        $this->assertEquals($record1->fingerprint->getValue(), $record2->fingerprint->getValue());
        $this->assertEquals($record1->data->toArray(), $record2->data->toArray());
    }

    public function test_convert_with_cluster_with_multiple_values(): void
    {
        $entity = new TestIndexableEntity(
            key: '789',
            morphClass: 'App\Models\Product',
            data: [
                'name' => 'Laptop',
                'price' => 999.99,
            ],
        );

        $cluster = new ClusterVO([
            'model' => 'Product',
            'tenant_company_abc' => true,
            'tenant_company_xyz' => true,
            'env' => 'production',
            'category_electronics' => true,
            'category_music' => true,
            'category_books' => true,
        ]);
        $record = IndexableRecordFactory::convert($entity, $cluster);

        $this->assertInstanceOf(IndexedDocumentRecord::class, $record);
        $this->assertEquals('App\Models\Product|789', $record->fingerprint->getValue());

        $this->assertNotNull($record->cluster);
        $this->assertEquals('Product', $record->cluster->get('model'));
        $this->assertEquals('true', $record->cluster->get('tenant_company_abc'));
        $this->assertEquals('true', $record->cluster->get('tenant_company_xyz'));
        $this->assertEquals('production', $record->cluster->get('env'));
        $this->assertEquals('true', $record->cluster->get('category_electronics'));
        $this->assertEquals('true', $record->cluster->get('category_music'));
        $this->assertEquals('true', $record->cluster->get('category_books'));
        $this->assertNull($record->cluster->get('nonexistent'));
    }
}
