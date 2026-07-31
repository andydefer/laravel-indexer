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
 * Eloquent model representing an indexed document.
 *
 * An indexed document stores the indexed representation of an Eloquent model,
 * including its fingerprint, cluster data, and the actual document content.
 *
 * @property string $id The unique identifier of the indexed document
 * @property StrictAssociative $data The document data as a key-value collection
 * @property-read Collection<int, IndexedToken> $tokens The tokens associated with this document
 * @property-read int|null $tokens_count The number of tokens associated with this document
 * @property-read IndexableFingerPrintVO $fingerprint The document fingerprint (namespace|id)
 * @property-read ClusterVO $cluster The document cluster as a value object
 * @property-read string $namespace The namespace extracted from the fingerprint
 * @property-read string $entity_id The entity ID extracted from the fingerprint
 * @property-read array<string> $fields The list of field names present in the data
 * @property-read bool $has_fields Whether the document has any data fields
 */
final class IndexedDocument extends Model
{
    use HasUuids;

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'indexed_documents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'fingerprint',
        'cluster',
        'data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cluster' => ClusterCast::class,
        'data' => 'array',
    ];

    // =============================================
    // Relations
    // =============================================

    /**
     * Defines the one-to-many relationship with IndexedToken.
     *
     * @return HasMany<IndexedToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(IndexedToken::class, 'document_id');
    }

    // =============================================
    // Attribute Casts
    // =============================================

    /**
     * Casts the fingerprint attribute to an IndexableFingerPrintVO.
     *
     * @return Attribute<IndexableFingerPrintVO, never>
     */
    protected function fingerprint(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): IndexableFingerPrintVO => new IndexableFingerPrintVO($attributes['fingerprint']),
        );
    }

    /**
     * Casts the data attribute to a StrictAssociative collection.
     *
     * @return Attribute<StrictAssociative, never>
     */
    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): StrictAssociative => StrictAssociative::from(
                json_decode($attributes['data'], true) ?? []
            ),
        );
    }

    /**
     * Extracts the namespace from the fingerprint.
     *
     * @return Attribute<string, never>
     */
    protected function namespace(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => $this->fingerprint->getNamespace(),
        );
    }

    /**
     * Extracts the entity ID from the fingerprint.
     *
     * @return Attribute<string, never>
     */
    protected function entityId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): string => $this->fingerprint->getId(),
        );
    }

    /**
     * Returns the list of field names present in the document data.
     *
     * @return Attribute<array<string>, never>
     */
    protected function fields(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): array => $this->data->keys(),
        );
    }

    /**
     * Determines whether the document has any data fields.
     *
     * @return Attribute<bool, never>
     */
    protected function hasFields(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): bool => ! $this->data->isEmpty(),
        );
    }

    // =============================================
    // Public Methods
    // =============================================

    /**
     * Checks whether the document contains a specific data field.
     *
     * @param  string  $field  The field name to check
     * @return bool True if the field exists, false otherwise
     */
    public function hasField(string $field): bool
    {
        return $this->data->has($field);
    }

    /**
     * Retrieves a field value from the document data.
     *
     * @param  string  $field  The field name to retrieve
     * @param  mixed  $default  The default value if the field does not exist
     * @return mixed The field value, or the default if not found
     */
    public function getField(string $field, mixed $default = null): mixed
    {
        return $this->data->get($field, $default);
    }

    /**
     * Converts the model to an IndexableDocumentRecord.
     *
     * This method is used when transforming the model for indexing operations.
     *
     * @return IndexedDocumentRecord The record representation of the document
     */
    public function toIndexableRecord(): IndexedDocumentRecord
    {
        return new IndexedDocumentRecord(
            fingerprint: $this->fingerprint,
            cluster: $this->cluster,
            data: $this->data,
        );
    }
}
