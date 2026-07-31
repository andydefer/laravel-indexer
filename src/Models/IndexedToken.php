<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Models;

use AndyDefer\LaravelIndexer\Enums\GramType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $document_id
 * @property GramType $token_type
 * @property string $token
 * @property string $field
 * @property string $original_text
 * @property int $frequency
 * @property-read IndexedDocument $document
 */
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

    // =============================================
    // Relations
    // =============================================

    public function document(): BelongsTo
    {
        return $this->belongsTo(IndexedDocument::class, 'document_id');
    }
}
