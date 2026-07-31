<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts\Repositories;

use AndyDefer\LaravelCluster\Enums\DatabaseDriver;
use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Repository interface for indexed document operations.
 *
 * @extends AbstractRepositoryInterface<IndexedDocument, IndexedDocumentRecord>
 */
interface IndexedDocumentRepositoryInterface extends AbstractRepositoryInterface
{
    // ==================== FIND BY FINGERPRINT ====================

    public function findByFingerPrint(IndexableFingerPrintVO $fingerprint): ?IndexedDocument;

    public function findByFingerprintString(string $fingerprint): ?IndexedDocument;

    // ==================== FIND BY NAMESPACE ====================

    public function findByNamespace(string $namespace): Collection;

    // ==================== FIND BY CLUSTER ====================

    /**
     * Find documents matching a cluster query expression.
     *
     * Les requêtes supportent les opérateurs :
     * - & ou AND pour le ET logique
     * - | ou OR pour le OU logique
     * - Parenthèses pour le regroupement
     * - Fonctions SQL : COUNT, SUM, AVG, MIN, MAX, LENGTH, EXISTS, HAS, ALL
     * - Sous-conditions : addresses[city=Kinshasa]
     * - Opérateurs spéciaux : * (EXISTS), # (NOT_EXISTS)
     *
     * @param  string  $query  La requête cluster (ex: 'status=active & role=admin')
     * @param  string  $column  La colonne contenant les données cluster
     * @param  DatabaseDriver|null  $driver  Le driver de base de données
     * @return Collection<IndexedDocument>
     *
     * @example
     * // Recherche simple
     * $repository->findByClusterQuery('status=active');
     *
     * // Recherche avec AND
     * $repository->findByClusterQuery('status=active & role=admin');
     *
     * // Recherche avec OR
     * $repository->findByClusterQuery('status=active | status=pending');
     *
     * // Recherche avec fonction SQL
     * $repository->findByClusterQuery('COUNT(addresses) > 2');
     *
     * // Recherche avec sous-condition
     * $repository->findByClusterQuery('addresses[city=Kinshasa]');
     */
    public function findByClusterQuery(
        string $query,
        string $column = 'cluster',
        ?DatabaseDriver $driver = null
    ): Collection;

    // ==================== FIND BY IDS ====================

    public function findByIds(array $ids): Collection;

    // ==================== DELETE ====================

    public function deleteByFingerPrint(IndexableFingerPrintVO $fingerprint): int;

    public function deleteByFingerprintString(string $fingerprint): int;

    public function deleteByNamespace(string $namespace): int;

    /**
     * Delete documents matching a cluster query expression.
     *
     * @param  string  $query  La requête cluster
     * @param  string  $column  La colonne contenant les données cluster
     * @return int Nombre de documents supprimés
     */
    public function deleteByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): int;

    // ==================== COUNT ====================

    public function countByNamespace(string $namespace): int;

    /**
     * Count documents matching a cluster query expression.
     *
     * @param  string  $query  La requête cluster
     * @param  string  $column  La colonne contenant les données cluster
     * @return int Nombre de documents correspondants
     */
    public function countByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): int;

    // ==================== EXISTS ====================

    public function existsByFingerPrint(IndexableFingerPrintVO $fingerprint): bool;

    public function existsByNamespace(string $namespace): bool;

    /**
     * Check if any document matches a cluster query expression.
     *
     * @param  string  $query  La requête cluster
     * @param  string  $column  La colonne contenant les données cluster
     * @return bool True si au moins un document correspond
     */
    public function existsByClusterQuery(
        string $query,
        string $column = 'cluster'
    ): bool;

    // ==================== DISTINCT VALUES ====================

    public function getDistinctNamespaces(): Collection;

    public function getDistinctClusterKeys(): Collection;

    public function getDistinctClusterValues(string $key): Collection;

    // ==================== UTILITY ====================

    public function findAllWithTokens(): Collection;

    public function getModel(): Model;
}
