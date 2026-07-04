<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Records\IndexableRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function tokens(): HasMany
    {
        return $this->hasMany(IndexedToken::class, 'document_id');
    }

    public function getFields(): array
    {
        return array_keys($this->data);
    }

    public function hasField(string $field): bool
    {
        return isset($this->data[$field]);
    }

    public function getNamespace(): string
    {
        return $this->getFingerPrintVO()->getNamespace();
    }

    public function getEntityId(): string
    {
        return $this->getFingerPrintVO()->getId();
    }

    public function toIndexableRecord(): IndexableRecord
    {
        return new IndexableRecord(
            finger_print: $this->getFingerPrintVO(),
            cluster: $this->getClusterVO(),
            data: StrictAssociative::from($this->data),
        );
    }

    public function getFingerPrintVO(): IndexableFingerPrintVO
    {
        return new IndexableFingerPrintVO($this->fingerprint);
    }

    public function getClusterVO(): ClusterVO
    {
        return new ClusterVO($this->cluster['value'] ?? '');
    }
}
