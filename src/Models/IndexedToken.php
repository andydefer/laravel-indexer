<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Models;

use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IndexedToken extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'indexed_tokens';

    protected $fillable = [
        'id',
        'document_id',
        'token_type',
        'token',
        'field',
        'original_text',
        'frequency',
    ];

    protected $casts = [
        'token_type' => GramType::class,
        'frequency' => 'integer',
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
}
