<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

/**
 * Record représentant une requête de recherche.
 *
 * @example
 * // Un seul cluster
 * $clusters = (new ClusterVOCollection())
 *     ->add(new ClusterVO('type:user|status:active@AND'));
 *
 * // Plusieurs clusters avec AND
 * $clusters = (new ClusterVOCollection())
 *     ->add(new ClusterVO('type:user@AND'))
 *     ->add(new ClusterVO('status:active@AND'));
 *
 * // Plusieurs clusters avec OR
 * $clusters = (new ClusterVOCollection())
 *     ->add(new ClusterVO('role_doctor:true@OR'))
 *     ->add(new ClusterVO('role_admin:true@OR'));
 *
 * $query = new SearchQueryRecord(
 *     query: new SearchQueryVO('john=name,description|doe=name'),
 *     clusters: $clusters,
 *     clustersOperator: 'AND', // ou 'OR', 'NOT'
 *     limit: 100,
 *     min_size: 2,
 *     max_size: 4,
 * );
 */
final class SearchQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly SearchQueryVO $query,
        public readonly ClusterVOCollection $clusters,
        public readonly string $clustersOperator = 'AND',
        public readonly ?int $limit = null,
        public readonly ?int $min_size = null,
        public readonly ?int $max_size = null,
    ) {}
}
