<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\PhpServices\Enums\NormalizationMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service for writing tokens to the index.
 *
 * Handles token generation, buffering, and batch insertion for efficient
 * indexing of document content.
 */
final class IndexWriter
{
    /** @var array<string, array<string, mixed>> */
    private array $tokenBuffer = [];

    /** @var array<string, int> */
    private array $incrementBuffer = [];

    private ?string $currentDocumentId = null;

    private int $bufferSize = 5000;

    private int $insertChunkSize = 1000;

    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
        private readonly TextNormalizerInterface $textNormalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly IndexerConfigInterface $config,
    ) {}

    /**
     * Indexes a single document record.
     *
     * @param  IndexedDocumentRecord  $entity  The document record to index
     */
    public function index(IndexedDocumentRecord $entity): void
    {
        $this->resetBuffers();

        $document = $this->createDocument($entity);
        $this->currentDocumentId = $document->id;

        $this->indexDocumentData($document, $entity->data->toArray());

        $this->flushTokens();
        $this->currentDocumentId = null;
    }

    /**
     * Indexes multiple document records.
     *
     * @param  IndexableRecordCollection  $records  The collection of document records to index
     */
    public function indexMany(IndexableRecordCollection $records): void
    {
        $this->resetBuffers();

        foreach ($records as $record) {
            $document = $this->createDocument($record);
            $this->currentDocumentId = $document->id;

            $this->indexDocumentData($document, $record->data->toArray());
        }

        $this->flushTokens();
        $this->currentDocumentId = null;
    }

    /**
     * Creates a new document from a record.
     *
     * @param  IndexedDocumentRecord  $record  The document record
     * @return IndexedDocument The created document
     */
    private function createDocument(IndexedDocumentRecord $record): IndexedDocument
    {
        $documentRecord = new IndexedDocumentRecord(
            fingerprint: $record->fingerprint,
            cluster: $record->cluster,
            data: $record->data,
        );

        return $this->documentRepository->create($documentRecord);
    }

    /**
     * Resets the token and increment buffers.
     */
    private function resetBuffers(): void
    {
        $this->tokenBuffer = [];
        $this->incrementBuffer = [];
    }

    /**
     * Indexes document data recursively.
     *
     * @param  IndexedDocument  $document  The document being indexed
     * @param  array<string, mixed>  $data  The data to index
     * @param  string  $prefix  The field prefix for nested data
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

            if (is_numeric($value) || is_bool($value)) {
                $this->extractAndBufferTokens($document->id, $field, (string) $value);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $this->extractAndBufferTokens($document->id, $field, $value);
        }
    }

    /**
     * Checks if an array is associative (not sequentially indexed).
     *
     * @param  array<int|string, mixed>  $array  The array to check
     * @return bool True if the array is associative
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Extracts tokens from a text value and adds them to the buffer.
     *
     * @param  string  $documentId  The document ID
     * @param  string  $field  The field name
     * @param  string  $value  The text value to tokenize
     */
    private function extractAndBufferTokens(string $documentId, string $field, string $value): void
    {
        $minSize = $this->config->getNgramMinSize();
        $maxSize = $this->config->getNgramMaxSize();

        if (empty($value)) {
            return;
        }

        if (strlen($value) > $this->config->getMaxTextLength()) {
            $value = substr($value, 0, $this->config->getMaxTextLength());
        }

        if (strlen($value) > $this->config->getFullTextMaxLength()) {
            $this->extractAndBufferTokensLong($documentId, $field, $value, $minSize, $maxSize);

            return;
        }

        $this->extractAndBufferTokensShort($documentId, $field, $value, $minSize, $maxSize);
    }

    /**
     * Extracts tokens from short text (under full text max length).
     *
     * @param  string  $documentId  The document ID
     * @param  string  $field  The field name
     * @param  string  $value  The text value to tokenize
     * @param  int  $minSize  The minimum n-gram size
     * @param  int  $maxSize  The maximum n-gram size
     */
    private function extractAndBufferTokensShort(
        string $documentId,
        string $field,
        string $value,
        int $minSize,
        int $maxSize
    ): void {
        $words = $this->extractWordsWithOriginalCase($value);
        $normalizedValue = $this->textNormalizer->normalize($value);
        $normalizedWords = $this->textNormalizer->extractWords($normalizedValue);

        $count = min(count($words), count($normalizedWords));

        for ($i = 0; $i < $count; $i++) {
            $originalWord = $words[$i] ?? $normalizedWords[$i];
            $normalizedWord = $normalizedWords[$i] ?? '';

            if (empty($normalizedWord)) {
                continue;
            }

            $this->processWord($documentId, $field, $normalizedWord, $originalWord, $minSize, $maxSize);
        }
    }

    /**
     * Extracts tokens from long text (over full text max length).
     *
     * Splits long text into chunks and processes each chunk separately.
     *
     * @param  string  $documentId  The document ID
     * @param  string  $field  The field name
     * @param  string  $value  The text value to tokenize
     * @param  int  $minSize  The minimum n-gram size
     * @param  int  $maxSize  The maximum n-gram size
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
        $words = $this->extractWordsWithOriginalCase($value);

        $index = 0;
        $totalWords = count($normalizedWords);
        $seenChunks = [];

        while ($index < $totalWords) {
            $chunkNormalized = '';
            $chunkOriginal = '';
            $chunkLength = 0;

            while ($index < $totalWords) {
                $word = $normalizedWords[$index] ?? '';
                $originalWord = $words[$index] ?? $word;
                $wordLength = strlen($word);

                if (empty($word)) {
                    $index++;

                    continue;
                }

                $newLength = $chunkLength + ($chunkLength > 0 ? 1 : 0) + $wordLength;

                if ($newLength > $this->config->getFullTextMaxLength() && $chunkLength > 0) {
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

            if ($chunkLength > $this->config->getFullTextMaxLength()) {
                $this->extractAndBufferTokensShort($documentId, $field, $chunkOriginal, $minSize, $maxSize);

                continue;
            }

            if ($chunkNormalized !== '' && ! in_array($chunkNormalized, $seenChunks)) {
                $seenChunks[] = $chunkNormalized;
                $chunkWords = explode(' ', $chunkNormalized);
                $chunkOriginalWords = explode(' ', $chunkOriginal);

                $count = min(count($chunkWords), count($chunkOriginalWords));

                for ($i = 0; $i < $count; $i++) {
                    $normalizedWord = $chunkWords[$i] ?? '';
                    $originalWord = $chunkOriginalWords[$i] ?? $normalizedWord;

                    if (empty($normalizedWord)) {
                        continue;
                    }

                    $this->processWord($documentId, $field, $normalizedWord, $originalWord, $minSize, $maxSize);
                }
            }
        }
    }

    /**
     * Processes a single word to generate lexical and metaphone n-grams.
     *
     * @param  string  $documentId  The document ID
     * @param  string  $field  The field name
     * @param  string  $normalizedWord  The normalized word
     * @param  string  $originalWord  The original word
     * @param  int  $minSize  The minimum n-gram size
     * @param  int  $maxSize  The maximum n-gram size
     */
    private function processWord(
        string $documentId,
        string $field,
        string $normalizedWord,
        string $originalWord,
        int $minSize,
        int $maxSize
    ): void {
        $phoneticMinSize = max(1, $minSize - 1);

        $ngrams = $this->ngramGenerator->generate(
            $normalizedWord,
            $minSize,
            $maxSize,
            NormalizationMode::WITH_NORMALIZATION
        );

        foreach ($ngrams as $ngram) {
            $this->addToBuffer($documentId, $ngram, $field, GramType::LEXICAL, $originalWord);
        }

        $metaphone = metaphone($normalizedWord);
        if ($metaphone !== false && ! empty($metaphone)) {
            $metaphoneNgrams = $this->ngramGenerator->generate(
                $metaphone,
                $phoneticMinSize,
                $maxSize,
                NormalizationMode::WITH_NORMALIZATION
            );

            foreach ($metaphoneNgrams as $metaphoneNgram) {
                $this->addToBuffer($documentId, $metaphoneNgram, $field, GramType::METAPHONE, $originalWord);
            }
        }
    }

    /**
     * Adds a token to the buffer or increments its frequency.
     *
     * @param  string  $documentId  The document ID
     * @param  string  $token  The token value
     * @param  string  $field  The field name
     * @param  GramType  $type  The token type
     * @param  string  $originalText  The original text
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
            $this->flushTokens();
        }
    }

    /**
     * Flushes the token and increment buffers to the database.
     *
     *
     * @throws \RuntimeException If the flush operation fails
     */
    private function flushTokens(): void
    {
        if (empty($this->tokenBuffer) && empty($this->incrementBuffer)) {
            return;
        }

        try {
            DB::beginTransaction();

            if (! empty($this->tokenBuffer)) {
                $toCreate = array_values($this->tokenBuffer);

                foreach (array_chunk($toCreate, $this->insertChunkSize) as $chunk) {
                    $this->tokenRepository->getModel()->newQuery()->insert($chunk);
                }
            }

            if (! empty($this->incrementBuffer)) {
                foreach ($this->incrementBuffer as $key => $count) {
                    $parts = explode('|', $key);
                    if (count($parts) === 4) {
                        [$docId, $token, $field, $type] = $parts;
                        $this->tokenRepository->getModel()->newQuery()
                            ->where('document_id', $docId)
                            ->where('token', $token)
                            ->where('field', $field)
                            ->where('token_type', $type)
                            ->increment('frequency', $count);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \RuntimeException('Failed to flush tokens: '.$e->getMessage(), 0, $e);
        }

        $this->resetBuffers();
    }

    /**
     * Extracts words from text while preserving original case.
     *
     * @param  string  $text  The text to extract words from
     * @return array<int, string> The extracted words
     */
    private function extractWordsWithOriginalCase(string $text): array
    {
        $words = preg_split('/[\s\-_\/]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values($words);
    }
}
