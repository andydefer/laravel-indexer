<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Models;

use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IndexedToken extends Model
{
    protected $table = 'indexed_tokens';

    protected $fillable = [
        'document_id',
        'token_type',
        'token',
        'field',
    ];

    protected $casts = [
        'token_type' => GramType::class,
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(IndexedDocument::class, 'document_id');
    }

    public function getFingerPrint(): IndexableFingerPrintVO
    {
        return $this->document->getFingerPrintVO();
    }

    public function getNamespace(): string
    {
        return $this->document->getNamespace();
    }

    public function getCluster(): ClusterVO
    {
        return $this->document->getClusterVO();
    }

    public function getClusterValue(string $key): ?string
    {
        return $this->document->getClusterVO()->get($key);
    }

    public function getGramType(): GramType
    {
        return $this->token_type;
    }

    public function isLexical(): bool
    {
        return $this->token_type === GramType::LEXICAL;
    }

    public function isMetaphone(): bool
    {
        return $this->token_type === GramType::METAPHONE;
    }

    /**
     * Scope pour les tokens commençant par une lettre
     */
    public function scopeStartingWith(Builder $query, string $letter): Builder
    {
        return $query->where('token', 'LIKE', $letter.'%');
    }

    /**
     * Scope pour l'autocomplétion
     */
    public function scopeAutocomplete(Builder $query, string $prefix): Builder
    {
        return $query->where('token', 'LIKE', $prefix.'%');
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType(Builder $query, GramType $type): Builder
    {
        return $query->where('token_type', $type);
    }

    /**
     * Scope pour filtrer par champ
     */
    public function scopeInField(Builder $query, string $field): Builder
    {
        return $query->where('field', $field);
    }

    /**
     * Scope pour filtrer par namespace (via la relation)
     */
    public function scopeInNamespace(Builder $query, string $namespace): Builder
    {
        return $query->whereHas('document', function (Builder $q) use ($namespace) {
            $q->where('fingerprint', 'LIKE', $namespace.'|%');
        });
    }

    /**
     * Scope pour filtrer par cluster (via la relation)
     */
    public function scopeInCluster(Builder $query, string $key, string $value): Builder
    {
        return $query->whereHas('document', function (Builder $q) use ($key, $value) {
            $q->whereJsonContains('cluster->'.$key, $value);
        });
    }
}
