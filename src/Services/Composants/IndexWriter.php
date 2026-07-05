<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\PhpServices\Enums\NormalizationMode;
use Illuminate\Support\Str;

/**
 * Service for writing/indexing documents and their tokens.
 *
 * Handles the full indexing pipeline:
 * - Document creation and persistence
 * - Token generation (lexical n-grams and metaphones)
 * - Buffering for bulk insertion
 * - Frequency tracking for duplicate tokens
 */
final class IndexWriter
{
    /** Maximum length for full-text chunking */
    private const FULL_TEXT_MAX_LENGTH = 25;

    /** Maximum text length to index (prevents token explosion) */
    private const MAX_TEXT_LENGTH = 100;

    /** @var array<string, array<string, mixed>> Buffer for new tokens */
    private array $tokenBuffer = [];

    /** @var array<string, int> Buffer for token frequency increments */
    private array $incrementBuffer = [];

    /** Maximum number of tokens before flushing */
    private int $bufferSize = 5000;

    /** Number of tokens per insert chunk (prevents MySQL placeholder limit) */
    private int $insertChunkSize = 1000;

    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
        private readonly TextNormalizerInterface $textNormalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly IndexerConfig $config,
    ) {}

    /**
     * Indexes a single document record.
     *
     * Creates the document, generates all tokens, and persists them to the index.
     *
     * @param  IndexedDocumentRecord  $entity  The record to index
     */
    public function index(IndexedDocumentRecord $entity): void
    {
        $this->resetBuffers();

        $documentRecord = new IndexedDocumentRecord(
            fingerprint: $entity->fingerprint,
            cluster: $entity->cluster,
            data: $entity->data
        );

        $document = $this->documentRepository->create($documentRecord);
        $this->indexDocumentData($document, $entity->data->toArray());
        $this->flushTokens($document->id);
    }

    /**
     * Indexes multiple document records.
     *
     * @param  IndexableRecordCollection  $records  Collection of records to index
     */
    public function indexMany(IndexableRecordCollection $records): void
    {
        $this->resetBuffers();

        foreach ($records as $record) {
            $documentRecord = new IndexedDocumentRecord(
                fingerprint: $record->fingerprint,
                cluster: $record->cluster,
                data: $record->data
            );

            $document = $this->documentRepository->create($documentRecord);
            $this->indexDocumentData($document, $record->data->toArray());
        }

        $this->flushTokens(null);
    }

    /**
     * Resets both token and increment buffers.
     */
    private function resetBuffers(): void
    {
        $this->tokenBuffer = [];
        $this->incrementBuffer = [];
    }

    /**
     * Recursively indexes document data, handling nested structures.
     *
     * @param  IndexedDocument  $document  The document being indexed
     * @param  array<mixed>  $data  The data to index
     * @param  string  $prefix  Field prefix for nested structures
     */
    private function indexDocumentData(IndexedDocument $document, array $data, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $field = $prefix ? $prefix.'.'.$key : $key;

            if (is_array($value)) {
                if ($this->isAssociativeArray($value)) {
                    $this->indexDocumentData($document, $value, $field);
                } else {
                    $concatenated = implode('; ', $value);
                    $this->extractAndBufferTokens($document->id, $field, $concatenated);
                }

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $this->extractAndBufferTokens($document->id, $field, $value);
        }
    }

    /**
     * Determines if an array is associative (vs sequential).
     *
     * @param  array<mixed>  $array  The array to check
     * @return bool True if associative, false if sequential
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Extracts tokens from a text value and adds them to the buffer.
     *
     * Handles text truncation and routes to short/long text processors.
     *
     * @param  string  $documentId  The document ID
     * @param  string  $field  The field name
     * @param  string  $value  The text value
     */
    private function extractAndBufferTokens(string $documentId, string $field, string $value): void
    {
        $minSize = $this->config->getNgramMinSize();
        $maxSize = $this->config->getNgramMaxSize();

        if (strlen($value) > self::MAX_TEXT_LENGTH) {
            $value = substr($value, 0, self::MAX_TEXT_LENGTH);
        }

        if (strlen($value) > self::FULL_TEXT_MAX_LENGTH) {
            $this->extractAndBufferTokensLong($documentId, $field, $value, $minSize, $maxSize);

            return;
        }

        $this->extractAndBufferTokensShort($documentId, $field, $value, $minSize, $maxSize);
    }

    /**
     * Processes short text by extracting tokens per word.
     */
    private function extractAndBufferTokensShort(
        string $documentId,
        string $field,
        string $value,
        int $minSize,
        int $maxSize
    ): void {
        $originalWords = $this->extractWordsPreserveCase($value);
        $normalizedValue = $this->textNormalizer->normalize($value);
        $normalizedWords = $this->textNormalizer->extractWords($normalizedValue);

        foreach ($normalizedWords as $index => $normalizedWord) {
            $originalWord = $originalWords[$index] ?? $normalizedWord;
            $this->processWord($documentId, $field, $normalizedWord, $originalWord, $minSize, $maxSize);
        }
    }

    /**
     * Processes long text by splitting into chunks and processing each chunk.
     */
    private function extractAndBufferTokensLong(
        string $documentId,
        string $field,
        string $value,
        int $minSize,
        int $maxSize
    ): void {
        $normalizedValue = $this->textNormalizer->normalize($value);
        $normalizedWords = $this->textNormalizer->extractWords($normalizedValue);
        $originalWords = $this->extractWordsPreserveCase($value);

        $index = 0;
        $totalWords = count($normalizedWords);
        $seenChunks = [];

        while ($index < $totalWords) {
            $chunkNormalized = '';
            $chunkOriginal = '';
            $chunkLength = 0;

            while ($index < $totalWords) {
                $word = $normalizedWords[$index];
                $originalWord = $originalWords[$index] ?? $word;
                $wordLength = strlen($word);

                $newLength = $chunkLength + ($chunkLength > 0 ? 1 : 0) + $wordLength;

                if ($newLength > self::FULL_TEXT_MAX_LENGTH && $chunkLength > 0) {
                    break;
                }

                if ($chunkLength === 0) {
                    $chunkNormalized = $word;
                    $chunkOriginal = $originalWord;
                    $chunkLength = $wordLength;
                } else {
                    $chunkNormalized .= ' '.$word;
                    $chunkOriginal .= ' '.$originalWord;
                    $chunkLength = $newLength;
                }

                $index++;
            }

            if ($chunkLength > self::FULL_TEXT_MAX_LENGTH) {
                $this->extractAndBufferTokensShort($documentId, $field, $chunkOriginal, $minSize, $maxSize);

                continue;
            }

            if ($chunkNormalized !== '' && ! in_array($chunkNormalized, $seenChunks)) {
                $seenChunks[] = $chunkNormalized;
                $chunkWords = explode(' ', $chunkNormalized);
                $chunkOriginalWords = explode(' ', $chunkOriginal);

                foreach ($chunkWords as $idx => $normalizedWord) {
                    $originalWord = $chunkOriginalWords[$idx] ?? $normalizedWord;
                    $this->processWord($documentId, $field, $normalizedWord, $originalWord, $minSize, $maxSize);
                }
            }
        }
    }

    /**
     * Processes a single word: generates lexical and metaphone n-grams.
     */
    private function processWord(
        string $documentId,
        string $field,
        string $normalizedWord,
        string $originalWord,
        int $minSize,
        int $maxSize
    ): void {
        $phoneticMinSize = $minSize - 1;

        // Generate LEXICAL n-grams
        $ngrams = $this->ngramGenerator->generate($normalizedWord, $minSize, $maxSize, NormalizationMode::WITH_NORMALIZATION);
        foreach ($ngrams as $ngram) {
            $this->addToBuffer($documentId, $ngram, $field, GramType::LEXICAL, $originalWord);
        }

        // Generate METAPHONE n-grams
        $metaphone = metaphone($normalizedWord);
        $metaphoneNgrams = $this->ngramGenerator->generate($metaphone, $phoneticMinSize, $maxSize, NormalizationMode::WITH_NORMALIZATION);
        foreach ($metaphoneNgrams as $metaphoneNgram) {
            $this->addToBuffer($documentId, $metaphoneNgram, $field, GramType::METAPHONE, $originalWord);
        }
    }

    /**
     * Adds a token to the buffer, handling duplicates via frequency increment.
     *
     * @param  string  $documentId  The document ID
     * @param  string  $token  The token value
     * @param  string  $field  The field name
     * @param  GramType  $type  The token type
     * @param  string  $originalText  The original text (preserves case)
     */
    private function addToBuffer(string $documentId, string $token, string $field, GramType $type, string $originalText): void
    {
        $key = $documentId.'|'.$token.'|'.$field.'|'.$type->value;

        if (isset($this->incrementBuffer[$key])) {
            $this->incrementBuffer[$key]++;

            return;
        }

        if (isset($this->tokenBuffer[$key])) {
            $this->tokenBuffer[$key]['frequency']++;

            return;
        }

        $now = now();
        $this->tokenBuffer[$key] = [
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'token_type' => $type->value,
            'token' => $token,
            'field' => $field,
            'original_text' => $originalText,
            'frequency' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ((count($this->tokenBuffer) + count($this->incrementBuffer)) >= $this->bufferSize) {
            $this->flushTokens($documentId);
        }
    }

    /**
     * Flushes buffered tokens to the database.
     *
     * Handles both new token insertion and frequency increments.
     *
     * @param  string|null  $documentId  Optional document ID filter (null = all)
     */
    private function flushTokens(?string $documentId = null): void
    {
        if (empty($this->tokenBuffer) && empty($this->incrementBuffer)) {
            return;
        }

        $toCreate = array_values($this->tokenBuffer);

        if (! empty($toCreate)) {
            foreach (array_chunk($toCreate, $this->insertChunkSize) as $chunk) {
                $this->tokenRepository->getModel()->newQuery()->insert($chunk);
            }
        }

        if (! empty($this->incrementBuffer)) {
            foreach ($this->incrementBuffer as $key => $count) {
                $parts = explode('|', $key);
                $this->tokenRepository->getModel()->newQuery()
                    ->where('document_id', $parts[0])
                    ->where('token', $parts[1])
                    ->where('field', $parts[2])
                    ->where('token_type', $parts[3])
                    ->increment('frequency', $count);
            }
        }

        $this->resetBuffers();
    }

    /**
     * Extracts words from text while preserving original case.
     *
     * Splits on spaces, hyphens, underscores, and slashes.
     *
     * @param  string  $text  The text to extract words from
     * @return array<string> Extracted words
     */
    private function extractWordsPreserveCase(string $text): array
    {
        $words = preg_split('/[\s\-_\/]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values($words);
    }
}
