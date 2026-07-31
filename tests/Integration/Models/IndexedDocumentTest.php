<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Integration\Models;

use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Tests\IntegrationTestCase;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use Illuminate\Database\Eloquent\Collection;

class IndexedDocumentTest extends IntegrationTestCase
{
    private IndexedDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->document = IndexedDocument::create([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'fingerprint' => 'App\Models\User|123',
            'cluster' => ([
                'type' => 'user',
                'status' => true,
                'email' => 'john@example.com',
                'addresses' => [
                    [
                        'city' => 'Paris',
                        'country' => 'France',
                        'postal_code' => '75000',
                        'street' => 'Rue de la Paix',
                    ],
                    [
                        'city' => 'Kinshase',
                        'country' => 'RDC',
                        'postal_code' => '458890',
                        'street' => 'Rue de la Joie',
                    ],
                ],
            ]),
            'data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'description' => 'Test user',
            ],
        ]);
    }

    public function test_create_indexed_document(): void
    {
        $this->assertInstanceOf(IndexedDocument::class, $this->document);
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $this->document->id);
        $this->assertEquals('App\Models\User|123', $this->document->fingerprint);
    }

    public function test_finger_print_attribute(): void
    {
        $fingerPrint = $this->document->finger_print;

        $this->assertInstanceOf(IndexableFingerPrintVO::class, $fingerPrint);
        $this->assertEquals('App\Models\User', $fingerPrint->getNamespace());
        $this->assertEquals('123', $fingerPrint->getId());
        $this->assertEquals('App\Models\User|123', $fingerPrint->getValue());
    }

    public function test_cluster_attribute(): void
    {
        $cluster = $this->document->cluster;

        $this->assertInstanceOf(ClusterVO::class, $cluster);
        $this->assertEquals('user', $cluster->get('type'));
        $this->assertTrue($cluster->get('status'));
        $this->assertEquals('john@example.com', $cluster->get('email'));
        $this->assertEquals('Paris', $cluster->get('addresses_0_city'));
        $this->assertEquals('France', $cluster->get('addresses_0_country'));
        $this->assertEquals('75000', $cluster->get('addresses_0_postal_code'));
        $this->assertEquals('Rue de la Paix', $cluster->get('addresses_0_street'));
    }

    public function test_namespace_attribute(): void
    {
        $this->assertEquals('App\Models\User', $this->document->namespace);
    }

    public function test_entity_id_attribute(): void
    {
        $this->assertEquals('123', $this->document->entity_id);
    }

    public function test_fields_attribute(): void
    {
        $fields = $this->document->fields;

        $this->assertIsArray($fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('description', $fields);
    }

    public function test_has_fields_attribute(): void
    {
        $this->assertTrue($this->document->has_fields);

        $emptyDocument = IndexedDocument::create([
            'id' => '550e8400-e29b-41d4-a716-446655440001',
            'fingerprint' => 'App\Models\User|124',
            'cluster' => json_encode(['type' => 'user']),
            'data' => [],
        ]);

        $this->assertFalse($emptyDocument->has_fields);
    }

    public function test_has_field_method(): void
    {
        $this->assertTrue($this->document->hasField('name'));
        $this->assertTrue($this->document->hasField('email'));
        $this->assertFalse($this->document->hasField('non_existent'));
    }

    public function test_to_indexable_record(): void
    {
        $record = $this->document->toIndexableRecord();

        $this->assertInstanceOf(IndexedDocumentRecord::class, $record);
        $this->assertEquals($this->document->finger_print->getValue(), $record->fingerprint->getValue());
        $this->assertInstanceOf(ClusterVO::class, $record->cluster);
        $this->assertEquals('user', $record->cluster->get('type'));
        $this->assertEquals('John Doe', $record->data->get('name'));
    }

    public function test_tokens_relation(): void
    {
        $token = IndexedToken::create([
            'id' => '660e8400-e29b-41d4-a716-446655440000',
            'document_id' => $this->document->id,
            'token_type' => GramType::LEXICAL,
            'token' => 'john',
            'field' => 'name',
            'original_text' => 'John',
            'frequency' => 1,
        ]);

        $this->assertInstanceOf(Collection::class, $this->document->tokens);
        $this->assertEquals(1, $this->document->tokens->count());
        $this->assertEquals($token->id, $this->document->tokens->first()->id);
    }

    public function test_cluster_is_stored_as_json(): void
    {
        $this->assertInstanceOf(ClusterVO::class, $this->document->cluster);
        $this->assertEquals('user', $this->document->cluster->get('type'));
        $this->assertTrue($this->document->cluster->get('status'));
    }

    public function test_data_is_stored_as_array(): void
    {
        $this->assertIsArray($this->document->data);
        $this->assertEquals('John Doe', $this->document->data['name']);
        $this->assertEquals('john@example.com', $this->document->data['email']);
    }
}
