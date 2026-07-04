<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\Models\IndexedDocument;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends AbstractRepositoryInterface<IndexedDocument, IndexedDocumentRecord>
 */
interface IndexedDocumentRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Trouve un document par son fingerprint.
     */
    public function findByFingerPrint(IndexableFingerPrintVO $fingerPrint): ?IndexedDocument;

    /**
     * Trouve un document par sa valeur de fingerprint brute.
     */
    public function findByFingerprintString(string $fingerprint): ?IndexedDocument;

    /**
     * Trouve tous les documents d'un namespace.
     */
    public function findByNamespace(string $namespace): Collection;

    /**
     * Trouve tous les documents d'un cluster.
     */
    public function findByCluster(ClusterVO $cluster): Collection;

    /**
     * Trouve tous les documents d'une paire cluster clé/valeur.
     */
    public function findByClusterKeyValue(string $key, string $value): Collection;

    /**
     * Trouve tous les documents par leurs IDs.
     *
     * @param  array<string>  $ids
     */
    public function findByIds(array $ids): Collection;

    /**
     * Supprime un document par son fingerprint.
     */
    public function deleteByFingerPrint(IndexableFingerPrintVO $fingerPrint): int;

    /**
     * Supprime un document par sa valeur de fingerprint brute.
     */
    public function deleteByFingerprintString(string $fingerprint): int;

    /**
     * Supprime tous les documents d'un namespace.
     */
    public function deleteByNamespace(string $namespace): int;

    /**
     * Supprime tous les documents d'un cluster.
     */
    public function deleteByCluster(ClusterVO $cluster): int;

    /**
     * Supprime tous les documents d'une paire cluster clé/valeur.
     */
    public function deleteByClusterKeyValue(string $key, string $value): int;

    /**
     * Compte les documents par namespace.
     */
    public function countByNamespace(string $namespace): int;

    /**
     * Compte les documents par cluster.
     */
    public function countByCluster(ClusterVO $cluster): int;

    /**
     * Récupère tous les namespaces distincts.
     *
     * @return Collection<int, string>
     */
    public function getDistinctNamespaces(): Collection;

    /**
     * Récupère toutes les clés de cluster distinctes.
     *
     * @return Collection<int, string>
     */
    public function getDistinctClusterKeys(): Collection;

    /**
     * Récupère toutes les valeurs de cluster pour une clé donnée.
     *
     * @return Collection<int, string>
     */
    public function getDistinctClusterValues(string $key): Collection;

    /**
     * Vérifie si un document existe par fingerprint.
     */
    public function existsByFingerPrint(IndexableFingerPrintVO $fingerPrint): bool;

    /**
     * Vérifie si un document existe par namespace.
     */
    public function existsByNamespace(string $namespace): bool;

    /**
     * Vérifie si un document existe par cluster.
     */
    public function existsByCluster(ClusterVO $cluster): bool;

    /**
     * Récupère tous les documents avec leurs tokens.
     */
    public function findAllWithTokens(): Collection;
}
