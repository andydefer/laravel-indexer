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

    public function index(IndexedDocumentRecord $entity): void
    {
        $this->resetBuffers();

        $document = $this->createDocument($entity);
        $this->currentDocumentId = $document->id;

        $this->indexDocumentData($document, $this->normal($entity->data->toArray()));

        $this->flushTokens();
        $this->currentDocumentId = null;
    }

    public function indexMany(IndexableRecordCollection $records): void
    {
        $this->resetBuffers();

        foreach ($records as $record) {
            $document = $this->createDocument($record);
            $this->currentDocumentId = $document->id;

            $this->indexDocumentData($document, $this->normal($record->data->toArray()));
        }

        $this->flushTokens();
        $this->currentDocumentId = null;
    }

    private function createDocument(IndexedDocumentRecord $record): IndexedDocument
    {
        $documentRecord = new IndexedDocumentRecord(
            fingerprint: $record->fingerprint,
            cluster: $record->cluster,
            data: $record->data,
        );

        return $this->documentRepository->create($documentRecord);
    }

    private function resetBuffers(): void
    {
        $this->tokenBuffer = [];
        $this->incrementBuffer = [];
    }

    private function normalizeValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => '',
            is_bool($value) => $value ? 'yes' : 'no',
            is_string($value) && strtolower($value) === 'true' => 'yes',
            is_string($value) && strtolower($value) === 'false' => 'no',
            default => (string) action_normalizer_chain(true)->normalize($value),
        };
    }

    private function indexDocumentData(IndexedDocument $document, array $data, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $field = $prefix ? $prefix.'.'.$key : $key;

            // ✅ Tableau associatif → parcours récursif
            if (is_array($value) && $this->isAssociativeArray($value)) {
                $this->indexDocumentData($document, $value, $field);

                continue;
            }

            // ❌ Tableau indexé → Exception
            if (is_array($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot index indexed array in field "%s". '
                    .'Structured data like lists should be stored in clusters, not in indexable data. '
                    .'Please move "%s" to getIndexableCluster() instead of getIndexableData(). '
                    .'Example: return ClusterVO::from([\'%s\' => $this->%s->toArray()]);',
                    $field,
                    $field,
                    $key,
                    $key
                ));
            }

            // ❌ Null → ignoré (ne donne aucune information)
            if (is_null($value)) {
                continue;
            }

            // ❌ Bool → Exception
            if (is_bool($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot index boolean value in field "%s". '
                    .'Boolean values should be stored in clusters for precise filtering, not in indexable data. '
                    .'Please move "%s" to getIndexableCluster() instead of getIndexableData(). '
                    .'Example: return ClusterVO::from([\'%s\' => $this->%s]);',
                    $field,
                    $field,
                    $key,
                    $key
                ));
            }

            // ❌ Numérique → Exception
            if (is_numeric($value) && ! is_string($value)) {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot index numeric value "%s" in field "%s". '
                    .'Numeric values should be stored in clusters for precise filtering, not in indexable data. '
                    .'Please move "%s" to getIndexableCluster() instead of getIndexableData(). '
                    .'Example: return ClusterVO::from([\'%s\' => $this->%s]);',
                    $value,
                    $field,
                    $field,
                    $key,
                    $key
                ));
            }

            // ✅ String → tokenisation
            if (is_string($value)) {
                $normalized = $this->normalizeValue($value);
                if (! empty($normalized)) {
                    $this->extractAndBufferTokens($document->id, $field, $normalized);
                }

                continue;
            }

            // ❌ Autres types (objets, resources) → Exception
            throw new \InvalidArgumentException(sprintf(
                'Cannot index value of type "%s" in field "%s". '
                .'Only string values should be indexed for text search. '
                .'Please convert this value to a string or move it to getIndexableCluster().',
                get_debug_type($value),
                $field
            ));
        }
    }

    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function extractAndBufferTokens(string $documentId, string $field, string $value): void
    {
        if (empty($value)) {
            return;
        }

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
        $words = $this->extractWords($value);
        $normalizedWords = $this->textNormalizer->extractWords($this->textNormalizer->normalize($value));

        $count = min(count($words), count($normalizedWords));

        for ($i = 0; $i < $count; $i++) {
            $originalWord = $words[$i] ?? $normalizedWords[$i];
            $normalizedWord = $normalizedWords[$i] ?? '';

            if (! empty($normalizedWord)) {
                $this->processWord($documentId, $field, $normalizedWord, $originalWord, $minSize, $maxSize);
            }
        }
    }

    private function extractAndBufferTokensLong(
        string $documentId,
        string $field,
        string $value,
        int $minSize,
        int $maxSize
    ): void {
        $normalizedWords = $this->textNormalizer->extractWords($this->textNormalizer->normalize($value));
        $words = $this->extractWords($value);

        $index = 0;
        $totalWords = count($normalizedWords);
        $seenChunks = [];
        $maxLength = $this->config->getFullTextMaxLength();

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

                if ($newLength > $maxLength && $chunkLength > 0) {
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

            if ($chunkLength > $maxLength) {
                $this->extractAndBufferTokensShort($documentId, $field, $chunkOriginal, $minSize, $maxSize);

                continue;
            }

            if ($chunkNormalized === '' || in_array($chunkNormalized, $seenChunks)) {
                continue;
            }

            $seenChunks[] = $chunkNormalized;
            $chunkWords = explode(' ', $chunkNormalized);
            $chunkOriginalWords = explode(' ', $chunkOriginal);

            $count = min(count($chunkWords), count($chunkOriginalWords));

            for ($i = 0; $i < $count; $i++) {
                $normalizedWord = $chunkWords[$i] ?? '';
                $originalWord = $chunkOriginalWords[$i] ?? $normalizedWord;

                if (! empty($normalizedWord)) {
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

    private function normal(mixed $data): mixed
    {
        $prefilterd = action_normalizer_chain(true)->normalize($data);

        return normalizer_chain(true)->normalize($prefilterd);
    }

    private function extractWords(string $text): array
    {
        return array_values(preg_split('/[\s\-_\/]+/', $text, -1, PREG_SPLIT_NO_EMPTY));
    }
}
