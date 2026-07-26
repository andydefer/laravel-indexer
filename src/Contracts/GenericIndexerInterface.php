<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use Illuminate\Database\Eloquent\Model;

interface GenericIndexerInterface
{
    public function setBatchSize(int $batchSize): self;

    public function setLimit(?int $limit): self;

    /**
     * Indexe un modèle spécifique.
     */
    public function index(Model&Indexable $model): void;

    /**
     * Indexe un modèle par son ID.
     */
    public function indexById(string $modelClass, int $id): void;

    /**
     * Indexe tous les modèles d'une classe.
     */
    public function indexAll(string $modelClass): void;

    /**
     * Réindexe tous les modèles d'une classe.
     */
    public function reindexAll(string $modelClass): void;

    /**
     * Supprime un modèle de l'index.
     */
    public function delete(Model&Indexable $model): void;

    /**
     * Supprime un modèle par son ID.
     */
    public function deleteById(string $modelClass, int $id): void;

    /**
     * Supprime tous les modèles d'une classe.
     */
    public function deleteAll(string $modelClass): void;

    /**
     * Rafraîchit un modèle dans l'index.
     */
    public function refresh(Model&Indexable $model): void;

    /**
     * Rafraîchit un modèle par son ID.
     */
    public function refreshById(string $modelClass, int $id): void;

    /**
     * Compte les documents indexés pour une classe.
     */
    public function countIndexed(string $modelClass): int;

    /**
     * Vérifie si un modèle est indexé.
     */
    public function exists(Model&Indexable $model): bool;

    /**
     * Vérifie si un modèle est indexé par son ID.
     */
    public function existsById(string $modelClass, int $id): bool;
}
