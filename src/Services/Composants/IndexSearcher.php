<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexableSearchResultRecord;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use Illuminate\Support\Collection;

/**
 * Service for searching the index and checking document existence.
 *
 * Provides search capabilities with support for:
 * - Lexical n-gram matching
 * - Metaphone phonetic matching
 * - Cluster filtering
 * - Fingerprint filtering
 * - Multi-ngram intersection (AND logic)
 */
final class IndexSearcher
{
    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
        private readonly TextNormalizerInterface $textNormalizer,
        private readonly IndexerConfigInterface $config,
    ) {}

    /**
     * Checks whether a document exists by its fingerprint.
     *
     * @param  IndexableFingerPrintVO  $fingerprint  The fingerprint to check
     * @return bool True if the document exists, false otherwise
     */
    public function exists(IndexableFingerPrintVO $fingerprint): bool
    {
        return $this->documentRepository->existsByFingerPrint($fingerprint);
    }

    /**
     * Executes a search query against the index.
     *
     * @param  SearchQueryRecord  $query  The search query containing n-grams, filters, and limits
     * @return IndexableSearchResultCollection<IndexableSearchResultRecord> Collection of search results
     */
    public function search(SearchQueryRecord $query): IndexableSearchResultCollection
    {
        $results = new IndexableSearchResultCollection;
        $allDocumentIds = [];

        $minSize = $this->resolveMinSize($query);
        $maxSize = $this->resolveMaxSize($query);

        foreach ($query->query->getNgrams() as $ngram) {
            $normalizedNgram = $this->textNormalizer->normalize($ngram);
            $fields = $query->query->getFieldsForNgram($ngram);

            $lexicalIds = $this->searchTokens(
                $normalizedNgram,
                $fields,
                $query->fingerprint,
                $query->cluster,
                GramType::LEXICAL,
                $minSize,
                $maxSize
            );

            $metaphoneIds = $this->searchTokens(
                $normalizedNgram,
                $fields,
                $query->fingerprint,
                $query->cluster,
                GramType::METAPHONE,
                $minSize,
                $maxSize
            );

            $ngramIds = $lexicalIds->merge($metaphoneIds)->unique()->values();
            $allDocumentIds[] = $ngramIds;
        }

        $finalIds = $this->intersectResults($allDocumentIds);

        if ($query->limit !== null && $query->limit > 0) {
            $finalIds = $finalIds->take($query->limit);
        }

        $documents = $this->documentRepository->findByIds($finalIds->toArray());

        foreach ($documents as $document) {
            $matchInfo = $this->findMatchInfo($document, $query, $minSize, $maxSize);

            if ($matchInfo !== null) {
                $results->add(new IndexableSearchResultRecord(
                    item: $document->toIndexableRecord(),
                    field: $matchInfo['field'],
                    gram_value: $matchInfo['gram_value'],
                    gram_type: $matchInfo['gram_type'],
                ));
            }
        }

        return $results;
    }

    /**
     * Resolves the minimum n-gram size from query or configuration.
     *
     * @param  SearchQueryRecord  $query  The search query
     * @return int The resolved minimum n-gram size
     */
    private function resolveMinSize(SearchQueryRecord $query): int
    {
        $configMin = $this->config->getNgramMinSize();
        $configMax = $this->config->getNgramMaxSize();

        $requestedMin = $query->min_size ?? $configMin;
        $requestedMax = $query->max_size ?? $configMax;

        if ($requestedMin > $requestedMax || $requestedMin > $configMax) {
            return $configMin;
        }

        return max($configMin, $requestedMin);
    }

    /**
     * Resolves the maximum n-gram size from query or configuration.
     *
     * @param  SearchQueryRecord  $query  The search query
     * @return int The resolved maximum n-gram size
     */
    private function resolveMaxSize(SearchQueryRecord $query): int
    {
        $configMin = $this->config->getNgramMinSize();
        $configMax = $this->config->getNgramMaxSize();

        $requestedMin = $query->min_size ?? $configMin;
        $requestedMax = $query->max_size ?? $configMax;

        if ($requestedMin > $requestedMax || $requestedMax < $configMin) {
            return $configMax;
        }

        return min($configMax, $requestedMax);
    }

    /**
     * Searches for tokens matching the given criteria.
     *
     * @param  string  $ngram  The n-gram to search for
     * @param  array<string>  $fields  The fields to search in
     * @param  IndexableFingerPrintVO|null  $fingerprint  Optional fingerprint filter
     * @param  ClusterVO|null  $cluster  Optional cluster filter
     * @param  GramType  $type  The token type (LEXICAL or METAPHONE)
     * @param  int  $minSize  Minimum n-gram size
     * @param  int  $maxSize  Maximum n-gram size
     * @return Collection<int, string> Collection of document IDs
     */
    private function searchTokens(
        string $ngram,
        array $fields,
        ?IndexableFingerPrintVO $fingerprint,
        ?ClusterVO $cluster,
        GramType $type,
        int $minSize,
        int $maxSize
    ): Collection {
        $query = $this->tokenRepository->getModel()->newQuery()
            ->where('token_type', $type);

        if ($type === GramType::LEXICAL) {
            $ngrams = $this->generateNgramsFromTerm($ngram, $minSize, $maxSize);

            if (empty($ngrams)) {
                return collect();
            }

            $query->whereIn('token', $ngrams);
        } else {
            $metaphone = strtolower(metaphone($ngram));
            $query->where('token', $metaphone);
        }

        if (! empty($fields)) {
            $query->whereIn('field', $fields);
        }

        if ($fingerprint !== null) {
            $query->whereHas('document', function ($q) use ($fingerprint): void {
                $q->where('fingerprint', $fingerprint->getValue());
            });
        }

        if ($cluster !== null) {
            $query->whereHas('document', function ($q) use ($cluster): void {
                $q->where('cluster', 'LIKE', '%'.$cluster->value.'%');
            });
        }

        return $query->pluck('document_id')->unique()->values();
    }

    /**
     * Computes the intersection of multiple result sets (AND logic).
     *
     * @param  array<Collection<int, string>>  $results  Array of document ID collections
     * @return Collection<int, string> Intersection of all result sets
     */
    private function intersectResults(array $results): Collection
    {
        if (empty($results)) {
            return collect();
        }

        $nonEmpty = array_filter($results, fn ($result) => $result->isNotEmpty());

        if (empty($nonEmpty)) {
            return collect();
        }

        $intersection = $nonEmpty[0];

        for ($i = 1; $i < count($nonEmpty); $i++) {
            $intersection = $intersection->intersect($nonEmpty[$i]);
        }

        return $intersection->values();
    }

    /**
     * Finds the match information for a document against the query.
     *
     * @param  IndexedDocument  $document  The document to check
     * @param  SearchQueryRecord  $query  The search query
     * @param  int  $minSize  Minimum n-gram size
     * @param  int  $maxSize  Maximum n-gram size
     * @return array{field: string, gram_value: string, gram_type: GramType}|null Match information or null if no match
     */
    private function findMatchInfo(
        IndexedDocument $document,
        SearchQueryRecord $query,
        int $minSize,
        int $maxSize
    ): ?array {
        foreach ($query->query->getNgrams() as $ngram) {
            $normalizedNgram = $this->textNormalizer->normalize($ngram);
            $fields = $query->query->getFieldsForNgram($ngram);

            $ngrams = $this->generateNgramsFromTerm($normalizedNgram, $minSize, $maxSize);

            if (empty($ngrams)) {
                continue;
            }

            // Try LEXICAL match first
            $token = $this->tokenRepository->getModel()->newQuery()
                ->where('document_id', $document->id)
                ->whereIn('token', $ngrams)
                ->where('token_type', GramType::LEXICAL)
                ->when(! empty($fields), fn ($q) => $q->whereIn('field', $fields))
                ->first();

            if ($token !== null) {
                return [
                    'field' => $token->field,
                    'gram_value' => $ngram,
                    'gram_type' => GramType::LEXICAL,
                ];
            }

            // Fallback to METAPHONE match
            $metaphone = strtolower(metaphone($normalizedNgram));
            $token = $this->tokenRepository->getModel()->newQuery()
                ->where('document_id', $document->id)
                ->where('token', $metaphone)
                ->where('token_type', GramType::METAPHONE)
                ->when(! empty($fields), fn ($q) => $q->whereIn('field', $fields))
                ->first();

            if ($token !== null) {
                return [
                    'field' => $token->field,
                    'gram_value' => $ngram,
                    'gram_type' => GramType::METAPHONE,
                ];
            }
        }

        return null;
    }

    /**
     * Generates all n-grams from a term within the given size range.
     *
     * @param  string  $term  The term to generate n-grams from
     * @param  int  $minSize  Minimum n-gram size
     * @param  int  $maxSize  Maximum n-gram size
     * @return string[] Unique n-grams
     */
    private function generateNgramsFromTerm(string $term, int $minSize, int $maxSize): array
    {
        $length = strlen($term);
        $ngrams = [];

        for ($size = $minSize; $size <= min($maxSize, $length); $size++) {
            for ($i = 0; $i <= $length - $size; $i++) {
                $ngrams[] = substr($term, $i, $size);
            }
        }

        return array_unique($ngrams);
    }
}
