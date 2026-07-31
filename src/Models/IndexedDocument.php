<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property array<array-key, mixed> $data
 * @property-read Collection<int, IndexedToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read IndexableFingerPrintVO $finger_print
 * @property-read ClusterVO $cluster
 * @property-read string $namespace
 * @property-read string $entity_id
 * @property-read array<string> $fields
 * @property-read bool $has_fields
 */
final class IndexedDocument extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'indexed_documents';

    protected $fillable = [
        'id',
        'fingerprint',
        'cluster',
        'data',
    ];

    protected $casts = [
        'cluster' => 'array',
        'data' => 'array',
    ];

    // =============================================
    // Relations
    // =============================================

    public function tokens(): HasMany
    {
        return $this->hasMany(IndexedToken::class, 'document_id');
    }

    // =============================================
    // Cast Attributes
    // =============================================

    /**
     * Get the fingerprint as a value object.
     *
     * @return Attribute<IndexableFingerPrintVO, never>
     */
    protected function fingerPrint(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): IndexableFingerPrintVO => new IndexableFingerPrintVO($attributes['fingerprint']),
        );
    }

    /**
     * Get the cluster as a value object from the laravel-cluster package.
     *
     * @return Attribute<ClusterVO, never>
     */
    protected function cluster(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ClusterVO => new ClusterVO(json_decode($attributes['cluster'], true)),
        );
    }

    /**
     * Get the namespace from the fingerprint.
     *
     * @return Attribute<string, never>
     */
    protected function namespace(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => $this->finger_print->getNamespace(),
        );
    }

    /**
     * Get the entity ID from the fingerprint.
     *
     * @return Attribute<string, never>
     */
    protected function entityId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => $this->finger_print->getId(),
        );
    }

    /**
     * Get the fields from the data array.
     *
     * @return Attribute<array<string>, never>
     */
    protected function fields(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): array => array_keys($this->data),
        );
    }

    /**
     * Check if the document has fields.
     *
     * @return Attribute<bool, never>
     */
    protected function hasFields(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): bool => ! empty($this->data),
        );
    }

    // =============================================
    // Public Methods
    // =============================================

    public function hasField(string $field): bool
    {
        return isset($this->data[$field]);
    }

    public function toIndexableRecord(): IndexedDocumentRecord
    {
        return new IndexedDocumentRecord(
            fingerprint: $this->finger_print,
            cluster: $this->cluster,
            data: StrictAssociative::from($this->data),
        );
    }
}
