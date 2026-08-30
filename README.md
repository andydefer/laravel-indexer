```markdown
# Laravel Indexer - Documentation Complète

## Table des matières

1. [Installation](#1-installation)
2. [Configuration](#2-configuration)
3. [Préparer votre modèle](#3-préparer-votre-modèle)
4. [Les Clusters contextuels](#4-les-clusters-contextuels)
5. [Indexer des données](#5-indexer-des-données)
6. [Rechercher des documents](#6-rechercher-des-documents)
7. [Syntaxe de recherche](#7-syntaxe-de-recherche)
8. [GenericIndexerService](#8-genericindexerservice)
9. [CLI avec GenericIndexModelsDirective](#9-cli-avec-genericindexmodelsdirective)
10. [Tâches d'indexation programmée](#10-tâches-dindexation-programmée)
11. [Autocomplétion](#11-autocomplétion)
12. [Supprimer des documents](#12-supprimer-des-documents)
13. [Repositories](#13-repositories)
14. [Collections](#14-collections)
15. [Référence des clusters](#15-référence-des-clusters)
16. [Cas d'usage concrets](#16-cas-dusage-concrets)
17. [Débogage et résolution des problèmes](#17-débogage-et-résolution-des-problèmes)
18. [Performance et bonnes pratiques](#18-performance-et-bonnes-pratiques)

---

## 1. Installation

### 1.1 Prérequis

- PHP 8.1 ou supérieur
- Laravel 10.x, 11.x, 12.x, 13.x, 14.x ou 15.x

### 1.2 Installation via Composer

```bash
composer require andydefer/laravel-indexer
```

### 1.3 Migrations

```bash
php artisan vendor:publish --tag=indexer-migrations
php artisan migrate
```

### 1.4 Configuration

```bash
php artisan vendor:publish --tag=indexer-config
```

---

## 2. Configuration

Le fichier de configuration `config/indexer.php` :

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Chemin de stockage
    |--------------------------------------------------------------------------
    */
    'storage_path' => storage_path('indexer'),

    /*
    |--------------------------------------------------------------------------
    | Types de tokens
    |--------------------------------------------------------------------------
    */
    'token_types' => [
        'ngrams' => [
            'min_size' => 3,  // Taille minimale des n-grams
            'max_size' => 5,  // Taille maximale des n-grams
        ],
        'metaphone' => true, // Activation de la recherche phonétique
    ],

    /*
    |--------------------------------------------------------------------------
    | Limite par défaut
    |--------------------------------------------------------------------------
    */
    'default_limit' => 100,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'enable_cache' => true,
    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Taille des lots
    |--------------------------------------------------------------------------
    */
    'batch_size' => 50,

    /*
    |--------------------------------------------------------------------------
    | Modèles à indexer automatiquement
    |--------------------------------------------------------------------------
    */
    'model_indexables' => [
        // App\Models\User::class,
        // App\Models\Hospital::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites de texte
    |--------------------------------------------------------------------------
    */
    'full_text_max_length' => 100, // Découpage des textes longs
    'max_text_length' => 1000,     // Longueur maximale d'un champ
];
```

### 2.1 Variables d'environnement

```env
INDEXER_BATCH_SIZE=100
INDEXER_FULL_TEXT_MAX_LENGTH=200
INDEXER_MAX_TEXT_LENGTH=2000
```

---

## 3. Préparer votre modèle

Votre modèle doit implémenter l'interface `Indexable`.

### 3.1 Règles d'indexation

| Type de donnée | Comportement | Explication |
|----------------|--------------|-------------|
| **String** | ✅ Indexé | Recherche textuelle |
| **Booléen PHP** (`true`/`false`) | ❌ Exception | Doit être dans les clusters |
| **Numérique** (`int`/`float`) | ❌ Exception | Doit être dans les clusters |
| **Tableau associatif** | ✅ Parcours récursif | Structure préservée (notation pointée) |
| **Tableau indexé** | ❌ Exception | Doit être dans les clusters |
| **Null** | ❌ Ignoré | Pas d'information |
| **Enum** | ✅ Indexé (valeur) | Converti en string |

### 3.2 Implémentation

```php
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements Indexable
{
    /**
     * Détermine si le modèle doit être indexé.
     */
    public function shouldBeIndexed(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    /**
     * Retourne les données à indexer.
     *
     * ⚠️ Seuls les STRINGS sont indexés.
     * Les booléens, numériques et tableaux indexés doivent être dans les clusters.
     */
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,           // ✅ String
            'email' => $this->email,         // ✅ String
            'bio' => $this->bio,             // ✅ String
            'skills' => $this->skills,       // ✅ String
            'city' => $this->city,           // ✅ String
            'country' => $this->country,     // ✅ String
            // ❌ 'is_active' => $this->is_active, // Bool → Cluster
            // ❌ 'age' => $this->age,             // Numeric → Cluster
            // ❌ 'tags' => ['php', 'laravel'],    // Indexed array → Cluster
        ]);
    }

    /**
     * Retourne le cluster contextuel du modèle.
     *
     * Les clusters permettent le filtrage contextuel.
     */
    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from([
            'type' => 'user',
            'tenant' => $this->tenant_id,
            'status' => $this->is_active ? 'active' : 'inactive',
            'role' => $this->role,
            'country' => $this->country,
            'city' => $this->city,
            'verified' => $this->email_verified_at !== null ? 'yes' : 'no',
            'age' => $this->age,                         // ✅ Numeric
            'tags' => $this->tags->toArray(),            // ✅ Array
        ]);
    }

    /**
     * Retourne la classe morph.
     */
    public function getMorphClass()
    {
        return self::class;
    }
}
```

### 3.3 Données imbriquées (associatives)

Les données imbriquées sont automatiquement aplaties pour l'indexation :

```php
public function getIndexableData(): StrictAssociative
{
    return StrictAssociative::from([
        'name' => 'John Doe',
        'profile' => [  // ✅ Associative array → parcours récursif
            'twitter' => '@johndoe',
            'github' => 'johndoe',
        ],
    ]);
}

// Données indexées :
// - name: John Doe
// - profile.twitter: @johndoe
// - profile.github: johndoe
```

### 3.4 Recherche par champ spécifique

```php
// Recherche dans le champ 'name'
$query = 'john=name';

// Recherche dans plusieurs champs
$query = 'john=name,email,bio';

// Recherche dans un champ imbriqué
$query = 'johndoe=profile.github';
```

---

## 4. Les Clusters contextuels

### 4.1 Qu'est-ce qu'un cluster ?

Un cluster est un **filtre contextuel** qui permet de restreindre les recherches à un contexte spécifique.

```php
// Exemple : Filtrer les utilisateurs actifs avec rôle admin
$cluster = ClusterVO::from([
    'status' => 'active',
    'role' => 'admin',
]);
```

### 4.2 Types de données à mettre dans les clusters

| Type | Exemple | Pourquoi |
|------|---------|----------|
| **Booléens** | `is_active: true` | Filtrage exact |
| **Numériques** | `price: 1000` | Filtrage précis (>=, <=) |
| **Énumérations** | `status: active` | Filtrage par état |
| **Listes/Tableaux** | `tags: ['php', 'laravel']` | Filtrage de listes |
| **Dates** | `created_at: 2024-01-01` | Filtrage temporel |

### 4.3 Création d'un cluster

```php
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// Création simple
$cluster = ClusterVO::from([
    'status' => 'active',
    'role' => 'admin',
    'tenant' => 'company_abc',
]);

// Création avec données imbriquées
$cluster = ClusterVO::from([
    'user' => [
        'status' => 'active',
        'role' => 'admin',
    ],
    'addresses' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
        ['city' => 'Paris', 'country' => 'France'],
    ],
]);
```

### 4.4 Accès aux données

```php
$cluster = ClusterVO::from([
    'status' => 'active',
    'role' => 'admin',
    'profile' => [
        'name' => 'John Doe',
    ],
]);

// Accès simple
$status = $cluster->get('status'); // 'active'

// Accès par notation pointée
$name = $cluster->get('profile.name'); // 'John Doe'

// Vérification d'existence
if ($cluster->has('profile.name')) {
    echo $cluster->get('profile.name');
}
```

### 4.5 Utilisation dans le modèle

```php
public function getIndexableCluster(): ClusterVO
{
    return ClusterVO::from([
        'type' => 'user',
        'status' => $this->is_active ? 'active' : 'inactive',
        'role' => $this->role,
        'tenant' => $this->tenant_id,
        'country' => $this->country,
        'city' => $this->city,
        'verified' => $this->email_verified_at !== null ? 'yes' : 'no',
        'age' => $this->age,
        'has_orders' => $this->orders()->count() > 0 ? 'yes' : 'no',
        'tags' => $this->tags->pluck('name')->toArray(),
    ]);
}
```

---

## 5. Indexer des données

### 5.1 Indexer un document

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;

class UserIndexer
{
    public function __construct(
        private readonly IndexerInterface $indexer
    ) {}

    public function indexUser(User $user): void
    {
        $cluster = $user->getIndexableCluster();
        $record = IndexableRecordFactory::convert($user, $cluster);
        $this->indexer->index($record);
    }
}
```

### 5.2 Indexer en masse

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;

class BulkIndexer
{
    public function __construct(
        private readonly IndexerInterface $indexer
    ) {}

    public function indexAllUsers(): void
    {
        $records = new IndexableRecordCollection();

        User::where('is_active', true)->chunk(100, function ($users) use ($records) {
            foreach ($users as $user) {
                $cluster = $user->getIndexableCluster();
                $records->add(IndexableRecordFactory::convert($user, $cluster));
            }

            $this->indexer->indexMany($records);
        });
    }
}
```

### 5.3 Rafraîchir un document

```php
public function updateUser(User $user): void
{
    $user->save();

    $cluster = $user->getIndexableCluster();
    $record = IndexableRecordFactory::convert($user, $cluster);

    $this->indexer->refresh($record);
}
```

### 5.4 Indexation avec GenericIndexerService

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;

class UserIndexer
{
    public function __construct(
        private readonly GenericIndexerInterface $indexer
    ) {}

    public function indexUser(int $userId): void
    {
        $this->indexer->indexById(User::class, $userId);
    }

    public function reindexAllUsers(): void
    {
        $this->indexer
            ->setBatchSize(50)
            ->setLimit(10000)
            ->reindexAll(User::class);
    }

    public function getIndexedCount(): int
    {
        return $this->indexer->countIndexed(User::class);
    }
}
```

---

## 6. Rechercher des documents

### 6.1 Recherche simple

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

class UserSearchService
{
    public function __construct(
        private readonly IndexerInterface $indexer
    ) {}

    public function search(string $query, int $limit = 20): array
    {
        $searchQuery = new SearchQueryRecord(
            query: new SearchQueryVO($query . '=name,email,bio'),
            limit: $limit
        );

        $results = $this->indexer->search($searchQuery);
        $userIds = $results->getIds()->toArray();

        return User::whereIn('id', $userIds)->get()->toArray();
    }
}

// Utilisation
$service = new UserSearchService($indexer);
$users = $service->search('john');
```

### 6.2 Recherche avec filtres de cluster

```php
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

$searchQuery = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    cluster_queries: new ClusterQueries([
        'cluster' => 'status=active & role=admin'
    ]),
    limit: 20
);

$results = $this->indexer->search($searchQuery);
```

### 6.3 Recherche multi-termes

```php
// Recherche 'john' dans 'name' ET 'doe' dans 'last_name'
$searchQuery = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|doe=last_name'),
    limit: 20
);

$results = $this->indexer->search($searchQuery);
```

### 6.4 Vérification d'existence

```php
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

$fingerprint = new IndexableFingerPrintVO('App\Models\User|123');
$exists = $this->indexer->exists($fingerprint);
```

---

## 7. Syntaxe de recherche

### 7.1 Format général

```
ngram=field1,field2|ngram2=field3|ngram3=field1,field4
```

### 7.2 Exemples

| Requête | Description |
|---------|-------------|
| `john=name` | Recherche "john" dans le champ "name" |
| `john=name,email` | Recherche "john" dans "name" ou "email" |
| `john=name\|doe=last_name` | Recherche "john" ET "doe" |
| `john=profile.twitter` | Recherche dans un champ imbriqué |

### 7.3 Comment fonctionne la recherche ?

1. Le terme est normalisé (minuscules, accents supprimés)
2. Le système génère tous les n-grams possibles du terme
3. Il recherche les tokens LEXICAL correspondants
4. Si aucun résultat, il recherche les tokens METAPHONE (phonétique)
5. Retourne les documents trouvés

**Exemple :**
- Indexé : "john" → tokens : ["joh", "ohn", "john"]
- Recherche "joh" → trouve "john" car "joh" est un token
- Recherche "jon" → trouve "john" via métaphone (JN → jn)

---

## 8. GenericIndexerService

Le `GenericIndexerService` est le service principal pour indexer vos modèles Eloquent.

### 8.1 Injection

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;

class DoctorIndexer
{
    public function __construct(
        private readonly GenericIndexerInterface $indexer
    ) {}
}
```

### 8.2 Indexation

```php
// Indexer un modèle
$doctor = Doctor::find(1);
$this->indexer->index($doctor);

// Indexer par ID
$this->indexer->indexById(Doctor::class, 1);

// Indexer tous les modèles
$this->indexer->indexAll(Doctor::class);

// Indexer avec batch et limite
$this->indexer
    ->setBatchSize(50)
    ->setLimit(1000)
    ->indexAll(Doctor::class);
```

### 8.3 Réindexation

```php
// Réindexer tous les modèles (supprime puis recrée)
$this->indexer->reindexAll(Doctor::class);
```

### 8.4 Suppression

```php
// Supprimer un modèle
$doctor = Doctor::find(1);
$this->indexer->delete($doctor);

// Supprimer par ID
$this->indexer->deleteById(Doctor::class, 1);

// Supprimer tous les modèles d'un type
$this->indexer->deleteAll(Doctor::class);
```

### 8.5 Rafraîchissement

```php
// Rafraîchir un modèle (supprime puis recrée si éligible)
$doctor = Doctor::find(1);
$this->indexer->refresh($doctor);

// Rafraîchir par ID
$this->indexer->refreshById(Doctor::class, 1);
```

### 8.6 Comptage et vérification

```php
// Compter les documents indexés
$count = $this->indexer->countIndexed(Doctor::class);

// Vérifier si un modèle est indexé
$doctor = Doctor::find(1);
$exists = $this->indexer->exists($doctor);

// Vérifier par ID
$exists = $this->indexer->existsById(Doctor::class, 1);
```

### 8.7 Exemple complet

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;

class IndexManagementService
{
    public function __construct(
        private readonly GenericIndexerInterface $indexer
    ) {}

    public function fullReindex(): void
    {
        // Configurer les lots
        $this->indexer->setBatchSize(50);

        // Réindexer tous les types
        $this->indexer->reindexAll(User::class);
        $this->indexer->reindexAll(Product::class);
        $this->indexer->reindexAll(Order::class);

        // Vérifier le résultat
        $count = $this->indexer->countIndexed(User::class);
        echo "Utilisateurs indexés: {$count}\n";
    }

    public function indexSpecificModels(array $ids): void
    {
        foreach ($ids as $id) {
            try {
                $this->indexer->indexById(User::class, $id);
            } catch (ModelNotFoundException $e) {
                echo "Utilisateur {$id} non trouvé\n";
            }
        }
    }

    public function cleanupInactive(): void
    {
        // Supprimer les utilisateurs inactifs de l'index
        $inactiveUsers = User::where('is_active', false)->get();
        foreach ($inactiveUsers as $user) {
            $this->indexer->delete($user);
        }
    }
}
```

---

## 9. CLI avec GenericIndexModelsDirective

### 9.1 Signature

```bash
index:models {batch=50} {limit=?} {models*} {--reindex} {--count} {--delete}
```

### 9.2 Options

| Option | Description |
|--------|-------------|
| `batch` | Taille des lots pour le chunking (défaut: 50) |
| `limit` | Nombre maximum d'éléments à indexer (optionnel) |
| `models*` | Liste des modèles à indexer (notation pointée: `App.Models.User`) |
| `--reindex` | Supprime puis réindexe tous les modèles |
| `--count` | Compte les documents indexés |
| `--delete` | Supprime tous les documents de l'index |

### 9.3 Exemples

```bash
# Indexer tous les modèles configurés
./bin/directive index:models [App.Models.User,App.Models.Hospital]

# Indexer avec batch=10 et limit=5
./bin/directive index:models 10 5 [App.Models.User]

# Compter les documents indexés
./bin/directive index:models [App.Models.User] --count

# Supprimer tout l'index des modèles
./bin/directive index:models [App.Models.User] --delete

# Réindexer avec batch et limit
./bin/directive index:models 20 10 [App.Models.User] --reindex

# Utiliser un alias
./bin/directive idx:models [App.Models.User]
```

### 9.4 Configuration

```php
// config/indexer.php
'model_indexables' => [
    App\Models\User::class,
    App\Models\Hospital::class,
    App\Models\Specialty::class,
],
```

---

## 10. Tâches d'indexation programmée

### 10.1 GenericOrchestratorRecurringTask

Cette tâche récurrente orchestre l'indexation de tous les modèles configurés.

**Fonctionnement :**
1. Récupère les modèles configurés depuis `model_indexables`
2. Pour chaque modèle, récupère les IDs éligibles (`shouldBeIndexed()`)
3. Découpe les IDs par lots (`batch_size`)
4. Enregistre une tâche `GenericIndexBatchUniqueTask` pour chaque lot

**Configuration :**
```php
// config/indexer.php
'batch_size' => 50,
'model_indexables' => [
    App\Models\User::class,
    App\Models\Hospital::class,
],
```

### 10.2 GenericIndexBatchUniqueTask

Cette tâche unique indexe un lot d'éléments.

**Payload :**
```json
{
    "items": [
        {"modelClass": "App\\Models\\User", "id": 1},
        {"modelClass": "App\\Models\\User", "id": 2},
        {"modelClass": "App\\Models\\Hospital", "id": 3}
    ]
}
```

**Fonctionnement :**
1. Reçoit une `IndexableVOCollection`
2. Récupère toutes les instances en **UNE SEULE requête par classe**
3. Pour chaque modèle, vérifie l'éligibilité (`shouldBeIndexed()`)
4. Supprime le document s'il existe déjà
5. Indexe le modèle avec son cluster dynamique

---

## 11. Autocomplétion

### 11.1 Autocomplétion simple

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;

class AutocompleteService
{
    public function __construct(
        private readonly IndexedTokenRepository $tokenRepository
    ) {}

    public function suggest(string $prefix): array
    {
        $tokens = $this->tokenRepository->autocomplete($prefix, 10);
        return $tokens->pluck('token')->toArray();
    }
}

// Utilisation
$suggestions = $service->suggest('jo');
// ['john', 'jonathan', 'joe', ...]
```

### 11.2 Autocomplétion avec contexte

```php
// Autocomplétion avec cluster
$tokens = $this->tokenRepository
    ->findByTokenAndClusterQuery($prefix, 'status=active');
```

### 11.3 Autocomplétion par champ

```php
$tokens = $this->tokenRepository->getModel()
    ->newQuery()
    ->where('token', 'LIKE', $prefix . '%')
    ->where('field', 'name')
    ->select('token')
    ->distinct()
    ->limit(10)
    ->get();
```

---

## 12. Supprimer des documents

### 12.1 Suppression unitaire

```php
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

$fingerprint = new IndexableFingerPrintVO('App\Models\User|123');
$this->indexer->delete($fingerprint);
```

### 12.2 Suppression par lots

```php
use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;

$collection = new IndexableFingerPrintVOCollection();
$collection->add(new IndexableFingerPrintVO('App\Models\User|1'));
$collection->add(new IndexableFingerPrintVO('App\Models\User|2'));
$collection->add(new IndexableFingerPrintVO('App\Models\Product|5'));

$this->indexer->deleteMany($collection);
```

### 12.3 Suppression par namespace

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;

$repository = app(IndexedDocumentRepository::class);
$repository->deleteByNamespace('App\Models\User');
```

### 12.4 Suppression par cluster

```php
$repository->deleteByClusterQuery('status=inactive');
```

### 12.5 Vider l'index

```php
$this->indexer->clear();
```

---

## 13. Repositories

### 13.1 IndexedDocumentRepository

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;

$repository = app(IndexedDocumentRepository::class);

// Recherche par fingerprint
$doc = $repository->findByFingerPrint($fingerprint);
$doc = $repository->findByFingerprintString('App\Models\User|123');

// Recherche par namespace
$docs = $repository->findByNamespace('App\Models\User');

// Recherche par cluster
$docs = $repository->findByClusterQuery('status=active & role=admin');

// Comptage
$count = $repository->countByNamespace('App\Models\User');
$count = $repository->countByClusterQuery('status=active');

// Vérification d'existence
$exists = $repository->existsByFingerPrint($fingerprint);
$exists = $repository->existsByNamespace('App\Models\User');

// Suppression
$repository->deleteByFingerPrint($fingerprint);
$repository->deleteByNamespace('App\Models\User');
$repository->deleteByClusterQuery('status=inactive');

// Valeurs distinctes
$namespaces = $repository->getDistinctNamespaces();
$keys = $repository->getDistinctClusterKeys();
$values = $repository->getDistinctClusterValues('status');

// Utilitaires
$docs = $repository->findAllWithTokens();
```

### 13.2 IndexedTokenRepository

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Enums\GramType;

$repository = app(IndexedTokenRepository::class);

// Recherche par token
$tokens = $repository->findByToken('john');
$tokens = $repository->findByTokenAndField('john', 'name');
$tokens = $repository->findByTokenAndType('john', GramType::LEXICAL);

// Recherche par token et cluster
$tokens = $repository->findByTokenAndClusterQuery('john', 'status=active');

// Recherche par champ
$tokens = $repository->findByField('name');

// Recherche par document
$tokens = $repository->findByDocumentId('uuid');
$tokens = $repository->findByNamespace('App\Models\User');

// Autocomplétion
$tokens = $repository->autocomplete('jo', 10);

// Document IDs
$ids = $repository->getDocumentIdsForToken('john');

// Comptage
$count = $repository->countDistinctTokens();
$count = $repository->countByField('name');

// Suppression
$repository->deleteByDocumentId('uuid');
$repository->deleteByToken('john');

// Utilitaires
$tokens = $repository->getDistinctTokens();
$fields = $repository->getDistinctFields();
$repository->incrementFrequency($tokenId);
```

---

## 14. Collections

### 14.1 IndexableSearchResultCollection

```php
$results = $this->indexer->search($query);

// Accès aux résultats
foreach ($results as $result) {
    $item = $result->item;              // IndexedDocumentRecord
    $fingerprint = $item->fingerprint;  // IndexableFingerPrintVO
    $field = $result->field;            // string
    $gram = $result->gram_value;        // string
    $type = $result->gram_type;         // GramType
}

// Filtrage
$byField = $results->filterByField('name');
$byType = $results->filterByGramType(GramType::LEXICAL);
$byNamespace = $results->filterByNamespace('App\Models\User');

// Extraction
$ids = $results->getIds();                // StringTypedCollection
$items = $results->getItems();            // IndexableRecordCollection
$fingerprints = $results->getFingerprints();

// Groupement
$byField = $results->groupByField();
$byNamespace = $results->groupByNamespace();
```

### 14.2 IndexableRecordCollection

```php
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;

$records = new IndexableRecordCollection();

// Ajout
$records->add($record);

// Découpage
$chunks = $records->chunk(100);

// Filtrage
$users = $records->filterByNamespace('App\Models\User');
$active = $records->filterByCluster('status', 'active');

// Extraction
$fingerprints = $records->getFingerprints();
$clusters = $records->getClusters();
$ids = $records->getIds();

// Recherche
$found = $records->findById('123');
$found = $records->findByIdAndNamespace('123', 'App\Models\User');

// Recherche textuelle
$withJohn = $records->searchTextInData('John');

// Tri
$sorted = $records->sortByDataField('name', true);

// Pluck
$names = $records->pluckDataField('name');
```

### 14.3 IndexableVOCollection

```php
use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;

$collection = new IndexableVOCollection();
$collection->add(new IndexableVO(User::class, 1));
$collection->add(new IndexableVO(User::class, 2));
$collection->add(new IndexableVO(Hospital::class, 3));

// Extraction
$ids = $collection->getIds(); // [1, 2, 3]
$classes = $collection->getModelClasses();

// Récupération optimisée (UNE SEULE requête par classe)
$instances = $collection->getModelInstances();

// Filtrage
$users = $collection->filterByModelClass(User::class);

// Vérification
$hasId = $collection->containsId(1);
$hasClass = $collection->containsModelClass(User::class);

// Groupement
$groups = $collection->groupByModelClass();
```

### 14.4 IndexableFingerPrintVOCollection

```php
use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

$fingerprints = new IndexableFingerPrintVOCollection();
$fingerprints->add(new IndexableFingerPrintVO('App\Models\User|1'));
$fingerprints->add(new IndexableFingerPrintVO('App\Models\User|2'));

// Filtrage
$users = $fingerprints->filterByNamespace('App\Models\User');

// Extraction
$ids = $fingerprints->getIds();
$namespaces = $fingerprints->getNamespaces();

// Vérification
$hasId = $fingerprints->containsId('1');

// Recherche
$fp = $fingerprints->findByValue('App\Models\User|1');
$fp = $fingerprints->findByIdAndNamespace('1', 'App\Models\User');

// Groupement
$grouped = $fingerprints->groupByNamespace();
```

---

## 15. Référence des clusters

### 15.1 Syntaxe des requêtes cluster

Les requêtes cluster permettent de filtrer les documents par leurs clusters.

| Opérateur | Description | Exemple |
|-----------|-------------|---------|
| `=` | Égalité | `status=active` |
| `!=` | Différent | `status!=inactive` |
| `<` | Inférieur | `age<18` |
| `>` | Supérieur | `age>18` |
| `<=` | Inférieur ou égal | `age<=18` |
| `>=` | Supérieur ou égal | `age>=18` |
| `&` ou `AND` | ET logique | `status=active & role=admin` |
| `\|` ou `OR` | OU logique | `status=active | role=admin` |
| `*` | EXISTS | `*email` (la clé existe) |
| `#` | NOT_EXISTS | `#deleted_at` (la clé est absente) |

### 15.2 Exemples

```php
// Égalité simple
$docs = $repository->findByClusterQuery('status=active');

// AND
$docs = $repository->findByClusterQuery('status=active & role=admin');

// OR
$docs = $repository->findByClusterQuery('status=active | status=pending');

// Fonction SQL
$docs = $repository->findByClusterQuery('COUNT(addresses) > 2');

// Sous-condition
$docs = $repository->findByClusterQuery('addresses[city=Kinshasa]');

// EXISTS
$docs = $repository->findByClusterQuery('*email');
```

### 15.3 Fonctions SQL disponibles

| Fonction | Description | Exemple |
|----------|-------------|---------|
| `COUNT(path)` | Nombre d'éléments | `COUNT(addresses) > 2` |
| `SUM(path)` | Somme des valeurs | `SUM(prices) > 500` |
| `AVG(path)` | Moyenne | `AVG(scores) >= 85` |
| `LENGTH(path)` | Longueur d'une chaîne | `LENGTH(name) > 5` |
| `JSON_LENGTH(path)` | Longueur d'un tableau JSON | `JSON_LENGTH(addresses) > 2` |

---

## 16. Cas d'usage concrets

### 16.1 Recherche d'utilisateurs

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

class UserSearchService
{
    public function __construct(
        private readonly IndexerInterface $indexer
    ) {}

    public function searchActiveAdmins(string $query): array
    {
        $searchQuery = new SearchQueryRecord(
            query: new SearchQueryVO($query . '=name,email,bio'),
            cluster_queries: new ClusterQueries([
                'cluster' => 'status=active & role=admin'
            ]),
            limit: 20
        );

        $results = $this->indexer->search($searchQuery);
        $userIds = $results->getIds()->toArray();

        return User::whereIn('id', $userIds)->get()->toArray();
    }

    public function searchByLocation(string $query, string $city): array
    {
        $searchQuery = new SearchQueryRecord(
            query: new SearchQueryVO($query . '=name,email,bio'),
            cluster_queries: new ClusterQueries([
                'cluster' => "city=$city"
            ]),
            limit: 20
        );

        $results = $this->indexer->search($searchQuery);
        $userIds = $results->getIds()->toArray();

        return User::whereIn('id', $userIds)->get()->toArray();
    }
}
```

### 16.2 Recherche de produits e-commerce

```php
<?php

namespace App\Services;

use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

class ProductSearchService
{
    public function __construct(
        private readonly IndexerInterface $indexer
    ) {}

    public function searchProducts(string $query, array $filters): array
    {
        $conditions = [];

        if (isset($filters['category'])) {
            $conditions[] = "category={$filters['category']}";
        }
        if (isset($filters['min_price'])) {
            $conditions[] = "price>={$filters['min_price']}";
        }
        if (isset($filters['max_price'])) {
            $conditions[] = "price<={$filters['max_price']}";
        }
        if (isset($filters['in_stock'])) {
            $conditions[] = "in_stock=" . ($filters['in_stock'] ? 'yes' : 'no');
        }

        $clusterQuery = implode(' & ', $conditions);

        $searchQuery = new SearchQueryRecord(
            query: new SearchQueryVO($query . '=name,description,tags'),
            cluster_queries: !empty($clusterQuery) ? new ClusterQueries([
                'cluster' => $clusterQuery
            ]) : null,
            limit: $filters['limit'] ?? 20
        );

        $results = $this->indexer->search($searchQuery);
        $productIds = $results->getIds()->toArray();

        return Product::whereIn('id', $productIds)->get()->toArray();
    }
}

// Utilisation
$service = new ProductSearchService($indexer);
$products = $service->searchProducts('laptop', [
    'category' => 'electronics',
    'min_price' => 500,
    'max_price' => 2000,
    'in_stock' => true,
    'limit' => 10,
]);
```

### 16.3 API REST avec recherche

```php
<?php

namespace App\Http\Controllers\Api;

use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

class UserController extends Controller
{
    public function __construct(
        private readonly IndexerInterface $indexer
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $role = $request->get('role');
        $status = $request->get('status');
        $city = $request->get('city');
        $limit = $request->get('limit', 20);

        $clusterConditions = [];

        if ($role) {
            $clusterConditions[] = "role=$role";
        }
        if ($status) {
            $clusterConditions[] = "status=$status";
        }
        if ($city) {
            $clusterConditions[] = "city=$city";
        }

        $searchQuery = new SearchQueryRecord(
            query: new SearchQueryVO($query . '=name,email,bio'),
            cluster_queries: !empty($clusterConditions) ? new ClusterQueries([
                'cluster' => implode(' & ', $clusterConditions)
            ]) : null,
            limit: $limit
        );

        $results = $this->indexer->search($searchQuery);
        $userIds = $results->getIds()->toArray();

        $users = User::whereIn('id', $userIds)
            ->select('id', 'name', 'email', 'role', 'city')
            ->get();

        return response()->json([
            'data' => $users,
            'meta' => [
                'total' => $users->count(),
                'limit' => $limit,
            ]
        ]);
    }
}

// Exemples d'appels API
// GET /api/users/search?q=john&role=admin
// GET /api/users/search?q=jane&status=active&city=Paris
// GET /api/users/search?q=doe&role=doctor&limit=10
```

---

## 17. Débogage et résolution des problèmes

### 17.1 Vérifier les modèles indexés

```bash
# Compter les documents indexés
./bin/directive index:models [App.Models.User] --count

# Voir le SQL généré
DB::enableQueryLog();
$users = User::whereCluster('clusters', 'status=active')->get();
dd(DB::getQueryLog());
```

### 17.2 Vérifier les clusters

```php
$user = User::find(1);
dd($user->getIndexableCluster()->toArray());
```

### 17.3 Problèmes courants

| Problème | Cause | Solution |
|----------|-------|----------|
| Aucun résultat | Requête invalide | Vérifier la syntaxe de recherche |
| Résultats incomplets | Token size trop petit | Augmenter `min_size` dans la config |
| Indexation lente | Batch size trop petit | Augmenter `batch_size` |
| Erreur de syntaxe | Parenthèses mal équilibrées | Vérifier les parenthèses dans la requête |
| Tableau indexé refusé | Données mal structurées | Déplacer dans les clusters |
| Booléen refusé | Type non pris en charge | Déplacer dans les clusters |
| Numérique refusé | Type non pris en charge | Déplacer dans les clusters |

### 17.4 Vérification de l'index

```bash
# Vérifier les documents indexés
./bin/directive index:models [App.Models.User] --count

# Supprimer et réindexer
./bin/directive index:models [App.Models.User] --reindex
```

---

## 18. Performance et bonnes pratiques

### 18.1 Indexation

```php
// ✅ Recommandé - Utiliser des batches
$this->indexer->setBatchSize(50)->indexAll(User::class);

// ✅ Recommandé - Filtrer avant d'indexer
User::where('is_active', true)->chunk(100, function ($users) {
    // Indexer uniquement les utilisateurs actifs
});

// ✅ Recommandé - Utiliser les clusters pour les filtres
public function getIndexableCluster(): ClusterVO
{
    return ClusterVO::from([
        'status' => $this->is_active ? 'active' : 'inactive',
        'role' => $this->role,
        'age' => $this->age,  // ✅ Numeric → cluster
        'is_verified' => $this->verified ? 'yes' : 'no',  // ✅ Boolean → cluster
        'tags' => $this->tags->toArray(),  // ✅ Array → cluster
    ]);
}

// ❌ À éviter - Indexer sans batch
User::all()->each(fn($user) => $this->indexer->index($user));

// ❌ À éviter - Indexer des données non-textuelles
public function getIndexableData(): StrictAssociative
{
    return StrictAssociative::from([
        'is_active' => $this->is_active,  // ❌ Boolean → exception
        'age' => $this->age,              // ❌ Numeric → exception
        'tags' => $this->tags->toArray(), // ❌ Indexed array → exception
    ]);
}
```

### 18.2 Recherche

```php
// ✅ Recommandé - Limiter les résultats
$searchQuery = new SearchQueryRecord(..., limit: 20);

// ✅ Recommandé - Utiliser les clusters pour filtrer
$searchQuery = new SearchQueryRecord(
    query: $query,
    cluster_queries: new ClusterQueries(['cluster' => 'status=active'])
);

// ❌ À éviter - Recherche sans limite
$searchQuery = new SearchQueryRecord(..., limit: null);
```

### 18.3 Configuration

```php
// Recommandations de configuration
return [
    'token_types' => [
        'ngrams' => [
            'min_size' => 3,  // Bon équilibre
            'max_size' => 5,  // Bon équilibre
        ],
        'metaphone' => true,  // Recherche phonétique
    ],
    'batch_size' => 100,  // Pour les gros volumes
    'full_text_max_length' => 200,  // Pour les textes longs
];
```

### 18.4 Récapitulatif des types de données

| Type | getIndexableData() | getIndexableCluster() |
|------|-------------------|----------------------|
| String (nom, description, bio) | ✅ Indexé | ❌ Non |
| Booléen (is_active, verified) | ❌ Exception | ✅ Filtrage |
| Numérique (price, age, quantity) | ❌ Exception | ✅ Filtrage |
| Enum (status, type, role) | ✅ Valeur string | ✅ Filtrage |
| Tableau associatif | ✅ Parcours récursif | ❌ Non |
| Tableau indexé (tags, listes) | ❌ Exception | ✅ Filtrage |
| Null | ❌ Ignoré | ❌ Ignoré |

---

## License

MIT © [Andy Defer](https://github.com/andydefer)
```