# Laravel Indexer

**Un système d'indexation puissant et flexible pour Laravel avec support d'Eloquent, tokenisation par n-grammes et métaphones, et capacités de recherche avancées.**

[![Version PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Version Laravel](https://img.shields.io/badge/Laravel-12.x%20|%2013.x%20|%2014.x%20|%2015.x-blue)](https://laravel.com)
[![Licence](https://img.shields.io/badge/Licence-MIT-green)](LICENSE)

---

## Table des matières

1. [Introduction](#introduction)
2. [Fonctionnalités](#fonctionnalités)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Structure des tables](#structure-des-tables)
6. [Concepts fondamentaux](#concepts-fondamentaux)
7. [Guide de démarrage](#guide-de-démarrage)
8. [Indexation des entités](#indexation-des-entités)
9. [Recherche](#recherche)
10. [Suppression et nettoyage](#suppression-et-nettoyage)
11. [Architecture détaillée](#architecture-détaillée)
12. [Performances](#performances)
13. [Bonnes pratiques](#bonnes-pratiques)
14. [Dépannage](#dépannage)
15. [Tests](#tests)
16. [License](#license)

---

## Introduction

**Laravel Indexer** est un package d'indexation full-text conçu pour Laravel qui transforme vos modèles Eloquent en documents recherchables. Il génère des tokens à partir de vos données (n-grammes lexicaux et métaphones phonétiques) et les stocke dans une base de données SQL, permettant des recherches ultra-rapides en **O(k)** où `k` est le nombre de résultats.

Contrairement aux solutions Elasticsearch ou Algolia qui nécessitent des services externes, Laravel Indexer fonctionne directement avec votre base de données existante, sans infrastructure supplémentaire.

### Comment ça fonctionne ?

1. **Indexation** : Vos entités sont transformées en documents avec des tokens (n-grammes et métaphones)
2. **Stockage** : Les documents et tokens sont persistés dans des tables SQL avec des index optimisés
3. **Recherche** : Les requêtes sont transformées en tokens et exécutées via des requêtes SQL optimisées

---

## Fonctionnalités

### Core

- ✅ **Recherche full-text** avec n-grammes (taille configurable : 3-5 par défaut)
- ✅ **Recherche phonétique** avec métaphones (tolérance aux fautes d'orthographe)
- ✅ **Filtrage avancé** par champ, cluster, namespace, fingerprint
- ✅ **Indexation en masse** avec bufferisation pour des performances optimales
- ✅ **Recherche multi-critères** (AND logique)
- ✅ **Architecture Repository** pour une séparation claire des responsabilités

### Stockage

- ✅ **Support de SQLite, MySQL, PostgreSQL**
- ✅ **Index automatiques** sur les colonnes de recherche
- ✅ **Bulk insert** pour l'indexation massive
- ✅ **Transactions** pour l'intégrité des données

### Développement

- ✅ **Injection de dépendances** et interfaces pour une intégration facile
- ✅ **Framework-agnostique** (utilise uniquement Laravel et PHP pur)
- ✅ **Type-safe** avec PHP 8.1+ (types stricts, readonly properties)
- ✅ **Tests unitaires et d'intégration** complets
- ✅ **Benchmarks** pour mesurer les performances

---

## Installation

```bash
composer require andydefer/laravel-indexer
```

### Prérequis

| Dépendance | Version |
|------------|---------|
| PHP | 8.1 ou supérieur |
| Laravel | 12.x, 13.x, 14.x ou 15.x |
| `andydefer/laravel-repository` | ^2.9.2 |
| `andydefer/laravel-directive` | ^3.31 |
| `andydefer/php-console` | ^1.2 |
| `andydefer/jsonl-cache` | ^0.3.7 |
| `andydefer/laravel-logger` | ^3.8 |
| `andydefer/inverted-index-search` | ^0.3.0 |

### Publier les migrations

```bash
php artisan vendor:publish --tag=indexer-migrations
php artisan migrate
```

### Publier la configuration (Optionnel)

```bash
php artisan vendor:publish --tag=indexer-config
```

---

## Configuration

Le fichier de configuration `config/indexer.php` :

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The path where index files will be stored (legacy, kept for compatibility).
    |
    */
    'storage_path' => storage_path('indexer'),

    /*
    |--------------------------------------------------------------------------
    | Token Types
    |--------------------------------------------------------------------------
    |
    | Configuration des tokens générés lors de l'indexation.
    |
    | min_size  : Taille minimale des n-grammes (défaut: 3)
    | max_size  : Taille maximale des n-grammes (défaut: 5)
    | metaphone : Activer/désactiver les métaphones (défaut: true)
    |
    | Note: Plus la plage est large, plus la recherche est précise,
    |       mais plus l'indexation est lente et l'espace de stockage important.
    */
    'token_types' => [
        'ngrams' => [
            'min_size' => 3,
            'max_size' => 5,
        ],
        'metaphone' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Limit
    |--------------------------------------------------------------------------
    |
    | Limite par défaut pour les résultats de recherche.
    |
    */
    'default_limit' => 100,

    /*
    |--------------------------------------------------------------------------
    | Enable Cache
    |--------------------------------------------------------------------------
    |
    | Mettre en cache les résultats de recherche (défaut: true).
    |
    */
    'enable_cache' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Durée de vie du cache en secondes (défaut: 3600).
    |
    */
    'cache_ttl' => 3600,
];
```

---

## Structure des tables

### Table `indexed_documents`

Stocke les documents indexés avec leurs métadonnées.

```sql
CREATE TABLE indexed_documents (
    id CHAR(36) PRIMARY KEY,                    -- UUID du document
    fingerprint VARCHAR(255) UNIQUE NOT NULL,    -- "App.Models.User|123"
    cluster VARCHAR(255) NOT NULL,              -- "model:User|tenant:company_abc"
    data JSON NOT NULL,                          -- Données indexées
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_fingerprint (fingerprint),
    INDEX idx_cluster (cluster)
);
```

### Table `indexed_tokens`

Stocke tous les tokens générés pour chaque document.

```sql
CREATE TABLE indexed_tokens (
    id CHAR(36) PRIMARY KEY,                    -- UUID du token
    document_id CHAR(36) NOT NULL,               -- Référence au document
    token_type VARCHAR(20) NOT NULL,            -- 'lexical' ou 'metaphone'
    token VARCHAR(255) NOT NULL,                -- La valeur du token
    field VARCHAR(255) NOT NULL,                -- Le champ source
    original_text VARCHAR(255) NOT NULL,        -- Texte original (casse préservée)
    frequency BIGINT UNSIGNED DEFAULT 1,        -- Fréquence d'apparition
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (document_id) REFERENCES indexed_documents(id) ON DELETE CASCADE,
    INDEX idx_token_field (token, field),
    INDEX idx_token_type_token (token_type, token),
    INDEX idx_token (token),
    INDEX idx_field (field)
);
```

---

## Concepts fondamentaux

### 1. IndexableRecord

Le `IndexableRecord` est un DTO (Data Transfer Object) qui représente un document à indexer.

```php
use AndyDefer\LaravelIndexer\Records\IndexableRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model:User|tenant:company_abc|env:production'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'description' => 'Software Developer'
    ])
);
```

### 2. Indexable (Interface)

Les entités que vous souhaitez indexer doivent implémenter l'interface `Indexable` :

```php
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

class User extends Model implements Indexable
{
    /**
     * Détermine si l'entité doit être indexée.
     */
    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    /**
     * Retourne les données à indexer.
     */
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'description' => $this->description,
        ]);
    }

    /**
     * Retourne l'ID de l'entité.
     */
    public function getKey(): int
    {
        return $this->id;
    }

    /**
     * Retourne le type de l'entité.
     */
    public function getMorphClass(): string
    {
        return self::class;
    }
}
```

### 3. Cluster

Le cluster est un système de **tags structurés** permettant de filtrer les résultats par catégorie.

```php
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

// Format: "clé:valeur|clé:valeur"
$cluster = new ClusterVO('model:User|tenant:company_abc|env:production');

// Récupération des valeurs (toujours un tableau)
$cluster->get('model');    // ['User']
$cluster->get('tenant');   // ['company_abc']
$cluster->get('env');      // ['production']

// Vérifications
$cluster->has('model');    // true
$cluster->has('unknown');  // false

// Support des valeurs multiples
$cluster = new ClusterVO('category:electronics,music,books');
$cluster->get('category'); // ['electronics', 'music', 'books']
```

### 4. Fingerprint

Le fingerprint est un identifiant unique combinant le type d'entité et son ID.

```php
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

$fingerprint = new IndexableFingerPrintVO('App.Models.User|123');

$fingerprint->getId();        // '123'
$fingerprint->getNamespace(); // 'App.Models.User'
$fingerprint->getValue();     // 'App.Models.User|123'
```

### 5. GramType

Enum définissant les types de tokens.

```php
use AndyDefer\LaravelIndexer\Enums\GramType;

GramType::LEXICAL;    // N-grammes lexicaux
GramType::METAPHONE;  // Métaphones phonétiques
```

---

## Guide de démarrage

### Étape 1 : Implémenter l'interface Indexable

```php
<?php

namespace App\Models;

use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements Indexable
{
    protected $fillable = ['id', 'name', 'reference', 'description', 'is_active'];

    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'reference' => $this->reference,
            'description' => $this->description,
        ]);
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }
}
```

### Étape 2 : Créer le cluster

```php
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

$cluster = new ClusterVO('model:Product|tenant:my_tenant|env:production');
```

### Étape 3 : Indexer une entité

```php
use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;

$product = Product::find(1);
$cluster = new ClusterVO('model:Product|tenant:company_abc|env:production');

$record = IndexableRecordFactory::convert($product, $cluster);

$indexer = app(IndexerService::class);
$indexer->index($record);
```

### Étape 4 : Rechercher

```php
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

$query = new SearchQueryRecord(
    query: new SearchQueryVO('laptop=name,description'),
    limit: 20
);

$results = $indexer->search($query);

foreach ($results as $result) {
    echo $result->item->data['name'] . "\n";
    echo "Matché dans: " . $result->field . "\n";
    echo "Token: " . $result->gram_value . "\n";
    echo "Type: " . $result->gram_type->value . "\n";
}
```

---

## Indexation des entités

### Indexation simple

```php
$record = IndexableRecordFactory::convert($entity, $cluster);
$indexer->index($record);
```

### Indexation en masse

```php
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;

$records = new IndexableRecordCollection();

foreach ($products as $product) {
    $records->add(IndexableRecordFactory::convert($product, $cluster));
}

$indexer->indexMany($records);
```

### Indexation avec données imbriquées

```php
$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model:User'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'profile' => [
            'bio' => 'Software Developer',
            'social' => [
                'twitter' => '@johndoe',
                'github' => 'johndoe'
            ]
        ],
        'tags' => ['php', 'laravel', 'mysql']
    ])
);

$indexer->index($record);
// Les tokens seront générés pour :
// - name
// - profile.bio
// - profile.social.twitter
// - profile.social.github
// - tags (concaténé en 'php; laravel; mysql')
```

### Rafraîchissement (update)

```php
// Met à jour un document existant (delete + index)
$indexer->refresh($record);

// Met à jour plusieurs documents
$indexer->refreshMany($records);
```

### Exemple complet d'indexation en masse

```php
<?php

use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

class ProductIndexer
{
    private IndexerService $indexer;
    private ClusterVO $cluster;

    public function __construct()
    {
        $this->indexer = app(IndexerService::class);
        $this->cluster = new ClusterVO('model:Product|tenant:my_tenant|env:production');
    }

    public function indexAll(): void
    {
        $products = Product::where('is_active', true)->get();
        $records = new IndexableRecordCollection();

        foreach ($products as $product) {
            $records->add(IndexableRecordFactory::convert($product, $this->cluster));
        }

        $this->indexer->indexMany($records);
        echo "Indexé " . $records->count() . " produits\n";
    }

    public function reindex(Product $product): void
    {
        $record = IndexableRecordFactory::convert($product, $this->cluster);
        $this->indexer->refresh($record);
    }

    public function delete(Product $product): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.Product|' . $product->id);
        $this->indexer->delete($fingerPrint);
    }
}
```

---

## Recherche

### Recherche simple

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name')
);
$results = $indexer->search($query);
```

### Recherche multi-champs (OR)

```php
// "john" dans "name" OU "description" OU "email"
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,description,email')
);
```

### Recherche multi-n-grams (AND)

```php
// "john" dans "name" ET "developer" dans "description"
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|developer=description')
);
```

### Recherche avec cluster

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    cluster: new ClusterVO('tenant:company_abc|env:production')
);
```

### Recherche avec fingerprint

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    finger_print: new IndexableFingerPrintVO('App.Models.User|123')
);
```

### Recherche avec limite personnalisée

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    limit: 50,
    min_size: 3,
    max_size: 5
);
```

### Recherche phonétique (métaphone)

```php
// "jon" est une faute d'orthographe de "john"
// Le métaphone de "jon" et "john" est identique ("JN")
$query = new SearchQueryRecord(
    query: new SearchQueryVO('jon=name')
);

$results = $indexer->search($query);
// Retourne les documents contenant "john" car "jon" est phonétiquement identique
```

### Exemple complet de recherche

```php
<?php

use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

class ProductSearch
{
    private IndexerService $indexer;

    public function __construct()
    {
        $this->indexer = app(IndexerService::class);
    }

    public function searchProducts(string $query, string $tenant = null): array
    {
        $searchQuery = new SearchQueryVO($query . '=name,reference,description');

        $record = new SearchQueryRecord(
            query: $searchQuery,
            cluster: $tenant ? new ClusterVO('tenant:' . $tenant) : null,
            limit: 50
        );

        $results = $this->indexer->search($record);

        $products = [];
        foreach ($results as $result) {
            $products[] = [
                'id' => $result->item->finger_print->getId(),
                'name' => $result->item->data['name'],
                'reference' => $result->item->data['reference'],
                'matched_field' => $result->field,
                'matched_term' => $result->gram_value,
                'match_type' => $result->gram_type->value,
            ];
        }

        return $products;
    }

    public function autocomplete(string $prefix): array
    {
        $query = new SearchQueryRecord(
            query: new SearchQueryVO($prefix . '=name'),
            limit: 10,
            min_size: 2,
            max_size: 3
        );

        $results = $this->indexer->search($query);

        return $results->map(fn($r) => $r->item->data['name'])->toArray();
    }
}
```

---

## Suppression et nettoyage

### Suppression d'un document

```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$indexer->delete($fingerPrint);
```

### Suppression en masse

```php
use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;

$collection = new IndexableFingerPrintVOCollection();
$collection->add(new IndexableFingerPrintVO('App.Models.User|123'));
$collection->add(new IndexableFingerPrintVO('App.Models.User|456'));
$collection->add(new IndexableFingerPrintVO('App.Models.Product|789'));

$indexer->deleteMany($collection);
```

### Suppression par namespace

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;

$documentRepo = new IndexedDocumentRepository();
$deleted = $documentRepo->deleteByNamespace('App.Models.User');
echo "Supprimé $deleted documents\n";
```

### Suppression par cluster

```php
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

$cluster = new ClusterVO('tenant:company_xyz');
$documentRepo = new IndexedDocumentRepository();
$deleted = $documentRepo->deleteByCluster($cluster);
echo "Supprimé $deleted documents\n";
```

### Nettoyage complet

```php
$indexer->clear(); // Supprime TOUS les documents et tokens
```

---

## Bonnes pratiques

### 1. Indexation en masse

```php
// ❌ À éviter : indexation un par un
foreach ($products as $product) {
    $indexer->index(IndexableRecordFactory::convert($product, $cluster));
}

// ✅ Recommandé : indexation en masse
$records = new IndexableRecordCollection();
foreach ($products as $product) {
    $records->add(IndexableRecordFactory::convert($product, $cluster));
}
$indexer->indexMany($records);
```

### 2. Utilisation des clusters

```php
// ❌ À éviter : clusters trop génériques
$cluster = new ClusterVO('type:product');

// ✅ Recommandé : clusters précis et structurés
$cluster = new ClusterVO('model:Product|tenant:company_abc|env:production|category:electronics');
```

### 3. Filtrage par champ

```php
// ❌ À éviter : rechercher dans tous les champs (lent)
$query = new SearchQueryVO('john=');

// ✅ Recommandé : spécifier les champs pertinents
$query = new SearchQueryVO('john=name,description');
```

### 4. Taille des n-grammes

```php
// ❌ À éviter : min_size trop petit (2) → trop de tokens
'min_size' => 2,

// ❌ À éviter : max_size trop grand (10) → trop de tokens
'max_size' => 10,

// ✅ Recommandé : plage équilibrée
'min_size' => 3,
'max_size' => 5,
```

### 5. Nettoyage régulier

```php
// ✅ Recommandé : nettoyer les documents inactifs
$inactiveProducts = Product::where('is_active', false)->get();
$fingerPrints = new IndexableFingerPrintVOCollection();
foreach ($inactiveProducts as $product) {
    $fingerPrints->add(new IndexableFingerPrintVO('App.Models.Product|' . $product->id));
}
$indexer->deleteMany($fingerPrints);
```

### 6. Utilisation des transactions

```php
// ✅ Recommandé : regrouper les opérations dans une transaction
DB::transaction(function () use ($products, $cluster, $indexer) {
    $records = new IndexableRecordCollection();
    foreach ($products as $product) {
        $records->add(IndexableRecordFactory::convert($product, $cluster));
    }
    $indexer->indexMany($records);
});
```

---

## Dépannage

### Erreur : `Prepared statement contains too many placeholders`

**Cause :** Trop de tokens dans une seule requête INSERT (limite MySQL = 65535).

**Solution :** Réduire `insertChunkSize` dans `IndexWriter` :

```php
private int $insertChunkSize = 500; // Au lieu de 1000
```

### Erreur : `Array to string conversion`

**Cause :** Les données contiennent des tableaux non encodés en JSON.

**Solution :** Utiliser `StrictAssociative` pour les données :

```php
$data = StrictAssociative::from([
    'name' => 'John Doe',
    'tags' => ['php', 'laravel'], // → sera encodé en JSON
]);
```

### Erreur : `Cluster cannot be empty`

**Cause :** Le cluster est vide ou mal formé.

**Solution :** Vérifier le format du cluster :

```php
// ❌ Mauvais
$cluster = new ClusterVO(''); // Exception

// ✅ Bon
$cluster = new ClusterVO('model:User|tenant:company_abc');
```

### Erreur : `Invalid cluster format`

**Cause :** Format incorrect (utilise `-` au lieu de `:`).

**Solution :** Utiliser le format `clé:valeur` :

```php
// ❌ Mauvais
$cluster = new ClusterVO('model-User');

// ✅ Bon
$cluster = new ClusterVO('model:User');
```

### Recherche lente

**Cause :** Manque d'index SQL ou requêtes non optimisées.

**Solution :**
1. Vérifier les index dans la table `indexed_tokens`
2. Réduire `min_size` et `max_size` pour moins de tokens
3. Utiliser des clusters pour filtrer avant la recherche

---

## Tests

### Exécuter les tests unitaires

```bash
./vendor/bin/phpunit
```

### Exécuter les tests d'intégration

```bash
./vendor/bin/phpunit --testsuite Integration
```

### Exécuter les benchmarks

```bash
./vendor/bin/phpunit --testsuite Benchmark
```

### Exécuter un test spécifique

```bash
./vendor/bin/phpunit --filter test_index_creates_document_and_tokens
```

---

## License

MIT © [Andy Kani](https://github.com/andydefer)