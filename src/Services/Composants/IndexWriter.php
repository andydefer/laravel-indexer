<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexableRecord;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\PhpServices\Contracts\Services\NGramGeneratorInterface;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use AndyDefer\PhpServices\Enums\NormalizationMode;
use Illuminate\Support\Str;

final class IndexWriter
{
    private array $tokenBuffer = [];

    private array $incrementBuffer = [];

    private int $bufferSize = 500;

    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
        private readonly TextNormalizerInterface $textNormalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly IndexerConfig $config,
    ) {}

    public function index(IndexableRecord $entity): void
    {
        $this->tokenBuffer = [];
        $this->incrementBuffer = [];

        $documentRecord = new IndexedDocumentRecord(
            fingerprint: $entity->finger_print,
            cluster: $entity->cluster,
            data: $entity->data
        );

        $document = $this->documentRepository->create($documentRecord);

        $this->indexData($document, $entity->data->toArray());

        $this->flushTokens($document->id);
    }

    public function indexMany(IndexableRecordCollection $records): void
    {
        foreach ($records as $record) {
            $this->index($record);
        }
    }

    private function indexData(IndexedDocument $document, array $data, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $field = $prefix ? $prefix.'.'.$key : $key;

            if (is_array($value)) {
                if ($this->isAssociativeArray($value)) {
                    $this->indexData($document, $value, $field);
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

        // Extraire les mots du texte ORIGINAL (non normalisé) pour garder la casse
        $originalWords = $this->extractWordsPreserveCase($value);

        // Normaliser le texte pour générer les tokens
        $normalizedValue = $this->textNormalizer->normalize($value);
        $normalizedWords = $this->textNormalizer->extractWords($normalizedValue);

        foreach ($normalizedWords as $index => $normalizedWord) {
            // Récupérer le mot original correspondant (avec la casse)
            $originalWord = $originalWords[$index] ?? $normalizedWord;

            // N-grammes LEXICAL
            $ngrams = $this->ngramGenerator->generate($normalizedWord, $minSize, $maxSize, NormalizationMode::WITH_NORMALIZATION);
            foreach ($ngrams as $ngram) {
                $this->addToBuffer($documentId, $ngram, $field, GramType::LEXICAL, $originalWord);
            }

            // Metaphone → n-grammes METAPHONE
            $metaphone = metaphone($normalizedWord);
            $metaphoneNgrams = $this->ngramGenerator->generate($metaphone, $minSize, $maxSize, NormalizationMode::WITH_NORMALIZATION);
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

        $this->tokenBuffer[$key] = [
            'id' => (string) Str::uuid(),
            'document_id' => $documentId,
            'token_type' => $type->value,
            'token' => $token,
            'field' => $field,
            'original_text' => $originalText,
            'frequency' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (count($this->tokenBuffer) + count($this->incrementBuffer) >= $this->bufferSize) {
            $this->flushTokens($documentId);
        }
    }

    private function flushTokens(string $documentId): void
    {
        if (empty($this->tokenBuffer) && empty($this->incrementBuffer)) {
            return;
        }

        $tokens = array_keys($this->tokenBuffer);
        $incrementKeys = array_keys($this->incrementBuffer);

        $allTokens = array_merge($tokens, $incrementKeys);
        $allTokens = array_unique($allTokens);

        if (! empty($allTokens)) {
            $existing = $this->tokenRepository->getModel()->newQuery()
                ->where('document_id', $documentId)
                ->whereIn('token', array_map(fn ($key) => explode('|', $key)[1], $allTokens))
                ->whereIn('field', array_map(fn ($key) => explode('|', $key)[2], $allTokens))
                ->get()
                ->keyBy(fn ($item) => $documentId.'|'.$item->token.'|'.$item->field.'|'.$item->token_type->value);

            $toCreate = [];
            $toIncrement = [];

            foreach ($this->tokenBuffer as $key => $data) {
                if (isset($existing[$key])) {
                    $toIncrement[] = $existing[$key]->id;
                } else {
                    $toCreate[] = $data;
                }
            }

            foreach ($this->incrementBuffer as $key => $count) {
                if (isset($existing[$key])) {
                    $toIncrement[] = $existing[$key]->id;
                } else {
                    $parts = explode('|', $key);
                    $toCreate[] = [
                        'id' => (string) Str::uuid(),
                        'document_id' => $documentId,
                        'token_type' => $parts[3],
                        'token' => $parts[1],
                        'field' => $parts[2],
                        'original_text' => '',
                        'frequency' => $count,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($toCreate)) {
                $this->tokenRepository->getModel()->newQuery()->insert($toCreate);
            }

            if (! empty($toIncrement)) {
                foreach ($toIncrement as $id) {
                    $this->tokenRepository->incrementFrequency($id);
                }
            }
        }

        $this->tokenBuffer = [];
        $this->incrementBuffer = [];
    }

    private function extractWordsPreserveCase(string $text): array
    {
        $words = preg_split('/[\s\-_\/]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values($words);
    }
}
