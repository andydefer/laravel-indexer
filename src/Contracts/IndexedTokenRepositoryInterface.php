<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Models\IndexedToken;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends AbstractRepositoryInterface<IndexedToken, IndexedTokenRecord>
 */
interface IndexedTokenRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Trouve les tokens par valeur.
     */
    public function findByToken(string $token): Collection;

    /**
     * Trouve les tokens par type.
     */
    public function findByType(GramType $type): Collection;

    /**
     * Trouve les tokens par champ.
     */
    public function findByField(string $field): Collection;

    /**
     * Trouve les tokens par document.
     */
    public function findByDocumentId(string $documentId): Collection;

    /**
     * Trouve les tokens par document fingerprint.
     */
    public function findByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): Collection;

    /**
     * Trouve les tokens par namespace.
     */
    public function findByNamespace(string $namespace): Collection;

    /**
     * Trouve les tokens par cluster.
     */
    public function findByCluster(ClusterVO $cluster): Collection;

    /**
     * Trouve les tokens par cluster clé/valeur.
     */
    public function findByClusterKeyValue(string $key, string $value): Collection;

    /**
     * Trouve les tokens par token et champ.
     */
    public function findByTokenAndField(string $token, string $field): Collection;

    /**
     * Trouve les tokens par token et type.
     */
    public function findByTokenAndType(string $token, GramType $type): Collection;

    /**
     * Trouve les tokens par token et namespace.
     */
    public function findByTokenAndNamespace(string $token, string $namespace): Collection;

    /**
     * Trouve les tokens par token et cluster.
     */
    public function findByTokenAndCluster(string $token, ClusterVO $cluster): Collection;

    /**
     * Trouve les tokens par token, champ et namespace.
     */
    public function findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection;

    /**
     * Recherche en autocomplétion.
     */
    public function autocomplete(string $prefix, ?int $limit = 10): Collection;

    /**
     * Recherche les tokens commençant par une lettre.
     */
    public function startingWith(string $letter, ?int $limit = null): Collection;

    /**
     * Récupère les IDs des documents pour un token.
     *
     * @return Collection<int, string>
     */
    public function getDocumentIdsForToken(string $token): Collection;

    /**
     * Récupère les IDs des documents pour un token et champ.
     *
     * @return Collection<int, string>
     */
    public function getDocumentIdsForTokenAndField(string $token, string $field): Collection;

    /**
     * Récupère les IDs des documents pour un token et cluster.
     *
     * @return Collection<int, string>
     */
    public function getDocumentIdsForTokenAndCluster(string $token, ClusterVO $cluster): Collection;

    /**
     * Récupère les IDs des documents pour un token, champ et cluster.
     *
     * @return Collection<int, string>
     */
    public function getDocumentIdsForTokenFieldAndCluster(string $token, string $field, ClusterVO $cluster): Collection;

    /**
     * Compte les tokens distincts.
     */
    public function countDistinctTokens(): int;

    /**
     * Compte les tokens par type.
     */
    public function countByType(GramType $type): int;

    /**
     * Compte les tokens par champ.
     */
    public function countByField(string $field): int;

    /**
     * Compte les tokens par namespace.
     */
    public function countByNamespace(string $namespace): int;

    /**
     * Supprime les tokens d'un document.
     */
    public function deleteByDocumentId(string $documentId): int;

    /**
     * Supprime les tokens par fingerprint.
     */
    public function deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): int;

    /**
     * Supprime les tokens par namespace.
     */
    public function deleteByNamespace(string $namespace): int;

    /**
     * Supprime les tokens par cluster.
     */
    public function deleteByCluster(ClusterVO $cluster): int;

    /**
     * Supprime les tokens par cluster clé/valeur.
     */
    public function deleteByClusterKeyValue(string $key, string $value): int;

    /**
     * Supprime les tokens par token.
     */
    public function deleteByToken(string $token): int;

    /**
     * Supprime les tokens par token et champ.
     */
    public function deleteByTokenAndField(string $token, string $field): int;

    /**
     * Récupère tous les tokens distincts.
     *
     * @return Collection<int, string>
     */
    public function getDistinctTokens(): Collection;

    /**
     * Récupère tous les champs distincts.
     *
     * @return Collection<int, string>
     */
    public function getDistinctFields(): Collection;
}
