<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Models;

use AndyDefer\LaravelIndexer\Enums\GramType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model representing an indexed token.
 *
 * An indexed token is a normalized n-gram extracted from a document field,
 * used for fast full-text search and fuzzy matching operations.
 *
 * Each token belongs to a document and stores both the normalized token
 * and the original text for relevance scoring.
 *
 * @property string $id The unique identifier of the token
 * @property string $document_id The foreign key to the indexed document
 * @property GramType $token_type The type of token (LEXICAL or METAPHONE)
 * @property string $token The normalized n-gram value
 * @property string $field The document field from which the token was extracted
 * @property string $original_text The original text before normalization
 * @property int $frequency The occurrence count of this token in the document
 * @property-read IndexedDocument $document The parent indexed document
 */
final class IndexedToken extends Model
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
    protected $table = 'indexed_tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'document_id',
        'token_type',
        'token',
        'field',
        'original_text',
        'frequency',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'token_type' => GramType::class,
        'frequency' => 'integer',
    ];

    // =============================================
    // Relations
    // =============================================

    /**
     * Defines the many-to-one relationship with IndexedDocument.
     *
     * @return BelongsTo<IndexedDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(IndexedDocument::class, 'document_id');
    }
}
