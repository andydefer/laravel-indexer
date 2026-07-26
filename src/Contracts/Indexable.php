<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

/**
 * Interface for entities that can be indexed.
 *
 * An entity is indexable if it can provide structured data for indexing,
 * including the data itself, a unique identifier, and a type identifier.
 */
interface Indexable
{
    /**
     * Determines whether the entity should be indexed.
     *
     * @return bool True if the entity is eligible for indexing, false otherwise
     */
    public function shouldBeIndexed(): bool;

    /**
     * Returns the data to be indexed as a StrictAssociative array.
     *
     * The array keys represent field names, and values represent the content
     * to be indexed for each field.
     *
     * @example
     * return StrictAssociative::from([
     *     'name' => $this->user->name,
     *     'email' => $this->user->email,
     *     'description' => $this->description,
     * ]);
     *
     * @return StrictAssociative<string, mixed> The indexable data
     */
    public function getIndexableData(): StrictAssociative;

    /**
     * Returns the unique identifier of the entity.
     *
     * @return int|string The entity's primary key or unique identifier
     */
    public function getKey();

    /**
     * Returns the entity's type identifier (morph class / namespace).
     *
     * This is used to group and filter indexed documents by entity type.
     *
     * @return string The fully qualified class name or type identifier
     */
    public function getMorphClass();

    /**
     * Returns the cluster configuration for the entity.
     *
     * The cluster is used to filter and group indexed documents for multi-tenant
     * or multi-context searches. It allows you to attach contextual metadata
     * like tenant, environment, region, category, status, etc.
     *
     * At minimum, the cluster MUST contain the entity type.
     *
     * @example
     * // Minimal cluster
     * return new ClusterVO('type:' . self::class);
     *
     * // Static cluster
     * return new ClusterVO('type:user|status:active');
     *
     * // Dynamic cluster based on entity data
     * return new ClusterVO(
     *     'type:user' .
     *     '|tenant:' . $this->tenant_id .
     *     '|role:' . $this->role .
     *     '|status:' . $this->status
     * );
     *
     * @return ClusterVO The cluster configuration
     */
    public function getIndexableCluster(): ClusterVO;
}
