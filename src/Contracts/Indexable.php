<?php

declare(strict_types=1);

namespace AndyDefer\LaravelIndexer\Contracts;

use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Interface pour les entités indexables.
 *
 * Une entité est indexable si elle peut fournir ses données à indexer.
 */
interface Indexable
{
    /**
     * Détermine si l'entité doit être indexée.
     */
    public function shouldBeIndexed(): bool;

    /**
     * Retourne les données à indexer sous forme de StrictAssociative.
     *
     * Clé = nom du champ, Valeur = contenu à indexer
     *
     * @example
     * return StrictAssociative::from([
     *     'name' => $this->user->name,
     *     'email' => $this->user->email,
     *     'description' => $this->description,
     * ]);
     */
    public function getIndexableData(): StrictAssociative;

    /**
     * Retourne l'ID de l'entité.
     */
    public function getKey();

    /**
     * Retourne le type de l'entité (morph class / namespace).
     */
    public function getMorphClass();
}
