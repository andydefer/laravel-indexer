<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Services\Composants;

use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Configs\IndexerConfig;
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

final class IndexSearcher
{
    public function __construct(
        private readonly IndexedDocumentRepository $documentRepository,
        private readonly IndexedTokenRepository $tokenRepository,
        private readonly TextNormalizerInterface $textNormalizer,
        private readonly IndexerConfig $config,
    ) {}

    public function exists(IndexableFingerPrintVO $finger_print): bool
    {
        return $this->documentRepository->existsByFingerPrint($finger_print);
    }

    public function search(SearchQueryRecord $query): IndexableSearchResultCollection
    {
        $results = new IndexableSearchResultCollection;
        $allDocumentIds = [];

        foreach ($query->query->getNgrams() as $ngram) {
            $normalizedNgram = $this->textNormalizer->normalize($ngram);
            $fields = $query->query->getFieldsForNgram($ngram);

            $lexicalIds = $this->searchTokens(
                $normalizedNgram,
                $fields,
                $query->finger_print,
                $query->cluster,
                GramType::LEXICAL
            );

            $metaphoneIds = $this->searchTokens(
                $normalizedNgram,
                $fields,
                $query->finger_print,
                $query->cluster,
                GramType::METAPHONE
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
            $matchInfo = $this->findMatchInfo($document, $query);

            if ($matchInfo) {
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

    private function searchTokens(
        string $ngram,
        array $fields,
        ?IndexableFingerPrintVO $fingerPrint,
        ?ClusterVO $cluster,
        GramType $type
    ): Collection {

        $query = $this->tokenRepository->getModel()->newQuery()
            ->where('token_type', $type);

        if ($type === GramType::LEXICAL) {
            $query->where('token', $ngram);
        } else {
            $metaphone = metaphone($ngram);
            $metaphoneLower = strtolower($metaphone);
            $query->where('token', $metaphoneLower);
        }

        if (! empty($fields)) {
            $query->whereIn('field', $fields);
        }

        if ($fingerPrint !== null) {
            $query->whereHas('document', function ($q) use ($fingerPrint) {
                $q->where('fingerprint', $fingerPrint->getValue());
            });
        }

        if ($cluster !== null) {
            $query->whereHas('document', function ($q) use ($cluster) {
                $q->where('cluster', 'LIKE', '%'.$cluster->value.'%');
            });
        }

        // Debug: voir la requête SQL
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $result = $query->pluck('document_id')->unique()->values();
        foreach ($result as $id) {
        }

        return $result;
    }

    private function intersectResults(array $results): Collection
    {

        if (empty($results)) {

            return collect();
        }

        $nonEmpty = array_filter($results, function ($result) {
            return $result->isNotEmpty();
        });

        foreach ($nonEmpty as $index => $result) {
            foreach ($result as $id) {
            }
        }

        if (empty($nonEmpty)) {

            return collect();
        }

        $intersection = $nonEmpty[0];
        for ($i = 1; $i < count($nonEmpty); $i++) {
            $intersection = $intersection->intersect($nonEmpty[$i]);
        }

        foreach ($intersection as $id) {
        }

        return $intersection->values();
    }

    private function findMatchInfo(IndexedDocument $document, SearchQueryRecord $query): ?array
    {
        foreach ($query->query->getNgrams() as $ngram) {
            $normalizedNgram = $this->textNormalizer->normalize($ngram);
            $fields = $query->query->getFieldsForNgram($ngram);

            // Recherche LEXICAL
            $token = $this->tokenRepository->getModel()->newQuery()
                ->where('document_id', $document->id)
                ->where('token', $normalizedNgram)
                ->where('token_type', GramType::LEXICAL)
                ->when(! empty($fields), function ($q) use ($fields) {
                    return $q->whereIn('field', $fields);
                })
                ->first();

            if ($token) {
                return [
                    'field' => $token->field,
                    'gram_value' => $ngram,
                    'gram_type' => GramType::LEXICAL,
                ];
            }

            // Recherche METAPHONE - UTILISER LE METAPHONE DU NGRAM
            $metaphone = strtolower(metaphone($normalizedNgram));
            $token = $this->tokenRepository->getModel()->newQuery()
                ->where('document_id', $document->id)
                ->where('token', $metaphone)
                ->where('token_type', GramType::METAPHONE)
                ->when(! empty($fields), function ($q) use ($fields) {
                    return $q->whereIn('field', $fields);
                })
                ->first();

            if ($token) {
                return [
                    'field' => $token->field,
                    'gram_value' => $ngram,
                    'gram_type' => GramType::METAPHONE,
                ];
            }
        }

        return null;
    }
}
