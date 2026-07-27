<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexableSearchResultRecord;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\PhpServices\Contracts\TextNormalizerInterface;
use Illuminate\Support\Collection;

final class IndexSearcher
{
    private ClusterFilterApplier $clusterFilterApplier;

    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
        private readonly TextNormalizerInterface $textNormalizer,
        private readonly IndexerConfigInterface $config,
    ) {
        $this->clusterFilterApplier = new ClusterFilterApplier;
    }

    public function exists(IndexableFingerPrintVO $fingerprint): bool
    {
        return $this->documentRepository->existsByFingerPrint($fingerprint);
    }

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
                $query->clusters,
                $query->clustersOperator,
                GramType::LEXICAL,
                $minSize,
                $maxSize
            );

            $metaphoneIds = $this->searchTokens(
                $normalizedNgram,
                $fields,
                $query->clusters,
                $query->clustersOperator,
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

    private function searchTokens(
        string $ngram,
        array $fields,
        ClusterVOCollection $clusters,
        ?string $clustersOperator,
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

        if ($clusters !== null && ! $clusters->isEmpty()) {
            $this->clusterFilterApplier->applyClustersOnRelation($query, $clusters, $clustersOperator);
        }

        return $query->pluck('document_id')->unique()->values();
    }

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
