<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\Casts\ClusterCast;
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
 * @property StrictAssociative $data
 * @property-read Collection<int, IndexedToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read IndexableFingerPrintVO $fingerprint
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
        'cluster' => ClusterCast::class,
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

    protected function fingerprint(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): IndexableFingerPrintVO => new IndexableFingerPrintVO($attributes['fingerprint']),
        );
    }

    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): StrictAssociative => StrictAssociative::from(
                json_decode($attributes['data'], true) ?? []
            ),
        );
    }

    protected function namespace(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => $this->fingerprint->getNamespace(),
        );
    }

    protected function entityId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => $this->fingerprint->getId(),
        );
    }

    protected function fields(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): array => $this->data->keys(),
        );
    }

    protected function hasFields(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): bool => ! $this->data->isEmpty(),
        );
    }

    // =============================================
    // Public Methods
    // =============================================

    public function hasField(string $field): bool
    {
        return $this->data->has($field);
    }

    public function getField(string $field, mixed $default = null): mixed
    {
        return $this->data->get($field, $default);
    }

    public function toIndexableRecord(): IndexedDocumentRecord
    {
        return new IndexedDocumentRecord(
            fingerprint: $this->fingerprint,
            cluster: $this->cluster,
            data: $this->data,
        );
    }
}
