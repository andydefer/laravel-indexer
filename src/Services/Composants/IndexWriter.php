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
use Illuminate\Support\Str;

final class IndexWriter
{
    private int $fullTextMaxLength;

    private int $maxTextLength;

    /** @var array<string, array<string, mixed>> */
    private array $tokenBuffer = [];

    /** @var array<string, int> */
    private array $incrementBuffer = [];

    private int $bufferSize = 5000;

    private int $insertChunkSize = 1000;

    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
        private readonly TextNormalizerInterface $textNormalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly IndexerConfigInterface $config,
    ) {}

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

    private function resetBuffers(): void
    {
        $this->tokenBuffer = [];
        $this->incrementBuffer = [];
    }

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

    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function extractAndBufferTokens(string $documentId, string $field, string $value): void
    {
        $minSize = $this->config->getNgramMinSize();
        $maxSize = $this->config->getNgramMaxSize();

        if (strlen($value) > $this->config->getMaxTextLength()) {
            $value = substr($value, 0, $this->config->getMaxTextLength());
        }

        if (strlen($value) > $this->config->getFullTextMaxLength()) {
            $this->extractAndBufferTokensLong($documentId, $field, $value, $minSize, $maxSize);

            return;
        }

        $this->extractAndBufferTokensShort($documentId, $field, $value, $minSize, $maxSize);
    }

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

                foreach ($chunkWords as $idx => $normalizedWord) {
                    $originalWord = $chunkOriginalWords[$idx] ?? $normalizedWord;
                    $this->processWord($documentId, $field, $normalizedWord, $originalWord, $minSize, $maxSize);
                }
            }
        }
    }

    private function processWord(
        string $documentId,
        string $field,
        string $normalizedWord,
        string $originalWord,
        int $minSize,
        int $maxSize
    ): void {
        $phoneticMinSize = $minSize - 1;

        $ngrams = $this->ngramGenerator->generate($normalizedWord, $minSize, $maxSize, NormalizationMode::WITH_NORMALIZATION);
        foreach ($ngrams as $ngram) {
            $this->addToBuffer($documentId, $ngram, $field, GramType::LEXICAL, $originalWord);
        }

        $metaphone = metaphone($normalizedWord);
        $metaphoneNgrams = $this->ngramGenerator->generate($metaphone, $phoneticMinSize, $maxSize, NormalizationMode::WITH_NORMALIZATION);
        foreach ($metaphoneNgrams as $metaphoneNgram) {
            $this->addToBuffer($documentId, $metaphoneNgram, $field, GramType::METAPHONE, $originalWord);
        }
    }

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

    private function extractWordsPreserveCase(string $text): array
    {
        $words = preg_split('/[\s\-_\/]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values($words);
    }
}
