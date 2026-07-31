<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Tests\Benchmark\Factories;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use Faker\Factory;
use Faker\Generator;

final class TestDataFactory
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('fr_FR');
    }

    public function createRecord(string $namespace = 'App.Models.User'): IndexedDocumentRecord
    {
        $id = $this->faker->unique()->numberBetween(1, 1000000);
        $fingerprint = new IndexableFingerPrintVO($namespace.'|'.$id);

        $clusterData = [
            'model' => 'User',
            'tenant' => $this->faker->company(),
            'env' => $this->faker->randomElement(['production', 'staging', 'development']),
            'region' => $this->faker->randomElement(['europe', 'america', 'asia']),
        ];

        $data = StrictAssociative::from([
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'postal_code' => $this->faker->postcode(),
            'description' => $this->faker->text(200),
            'bio' => $this->faker->text(500),
            'skills' => $this->faker->words(5),
            'languages' => $this->faker->randomElements(['French', 'English', 'Spanish', 'German']),
            'is_active' => $this->faker->boolean(80),
            'age' => $this->faker->numberBetween(18, 80),
            'salary' => $this->faker->numberBetween(30000, 150000),
        ]);

        return new IndexedDocumentRecord(
            fingerprint: $fingerprint,
            data: $data,
            cluster: new ClusterVO($clusterData),
        );
    }

    public function createRecords(int $count, string $namespace = 'App.Models.User'): array
    {
        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $records[] = $this->createRecord($namespace);
        }

        return $records;
    }

    public function createCollection(int $count, string $namespace = 'App.Models.User'): IndexableRecordCollection
    {
        $collection = new IndexableRecordCollection;
        foreach ($this->createRecords($count, $namespace) as $record) {
            $collection->add($record);
        }

        return $collection;
    }
}
