# Laravel Indexer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)
[![PHP Version Require](https://img.shields.io/packagist/php-v/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2F11%2F12%2F13%2F14%2F15-ff2d20.svg)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)

## Table des matières

- [Installation](#installation)
- [Préparer votre modèle](#préparer-votre-modèle)
- [Les Clusters](#les-clusters)
  - [Créer un Cluster](#créer-un-cluster)
  - [Lire un Cluster](#lire-un-cluster)
  - [Ajouter des valeurs](#ajouter-des-valeurs)
  - [Méthodes conditionnelles](#méthodes-conditionnelles)
  - [Supprimer des valeurs](#supprimer-des-valeurs)
  - [Vérification](#vérification)
  - [Méthodes statiques](#méthodes-statiques)
  - [Conversion](#conversion)
  - [Exemples avancés](#exemples-avancés)
- [Indexer des données](#indexer-des-données)
- [GenericIndexerService](#genericindexerservice)
- [GenericIndexModelsDirective](#genericindexmodelsdirective)
- [GenericOrchestratorRecurringTask](#genericorchestratorrecurringtask)
- [GenericIndexBatchUniqueTask](#genericindexbatchuniquetask)
- [Rechercher](#rechercher)
- [Autocomplétion](#autocomplétion)
- [Supprimer](#supprimer)
- [Repositories](#repositories)
- [Collections](#collections)

---

## Installation

```bash
composer require andydefer/laravel-indexer
```

### Migrations

```bash
php artisan vendor:publish --tag=indexer-migrations
php artisan migrate
```

### Configuration

```bash
php artisan vendor:publish --tag=indexer-config
```

```php
// config/indexer.php
return [
    'token_types' => [
        'ngrams' => [
            'min_size' => 3,
            'max_size' => 5,
        ],
        'metaphone' => true,
    ],
    'default_limit' => 100,
    'batch_size' => 50,
    'model_indexables' => [
        App\Models\User::class,
        App\Models\Hospital::class,
        App\Models\Specialty::class,
    ],
];
```

---

## Préparer votre modèle

Votre modèle doit implémenter l'interface `Indexable`.

```php
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements Indexable
{
    /**
     * Détermine si le modèle doit être indexé.
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
            'bio' => $this->bio,
            'skills' => $this->skills,
            'role' => $this->role,
            'profile' => [
                'twitter' => $this->twitter,
                'github' => $this->github,
            ],
        ]);
    }

    /**
     * Retourne l'identifiant unique du modèle.
     */
    public function getKey()
    {
        return $this->id;
    }

    /**
     * Retourne le nom de la classe morph.
     */
    public function getMorphClass()
    {
        return self::class;
    }

    /**
     * Génère le cluster dynamique du modèle.
     * 
     * Le cluster permet de filtrer les recherches par contexte
     * (tenant, environnement, rôle, statut, etc.).
     */
    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'user')
            ->withTernary('status', (bool) $this->is_active, 'active', 'inactive')
            ->whenNotEmpty('role', $this->role)
            ->whenKeyExists('tenant', $this->metadata ?? [], 'tenant')
            ->whenBool('verified', $this->email_verified_at !== null);
    }
}
```

---

## Les Clusters

Le cluster est un **filtre contextuel** permettant de filtrer les recherches par contexte (tenant, environnement, rôle, statut, etc.). C'est un élément clé de l'indexation qui permet une recherche multi-contextes.

### Format du cluster

```
key1:value1|key2:value2,value3|key3:value4,value5,value6
```

| Élément | Description |
|---------|-------------|
| `key:value` | Paire clé-valeur |
| `key:value1,value2` | Plusieurs valeurs pour une même clé |
| `|` | Séparateur de groupes |
| `,` | Séparateur de valeurs multiples |

### Créer un Cluster

#### Constructeur classique

```php
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

// Cluster simple
$cluster = new ClusterVO('tenant:company_abc');

// Cluster multi-attributs
$cluster = new ClusterVO('tenant:company_abc|env:production|region:europe');

// Cluster avec valeurs multiples
$cluster = new ClusterVO('tenant:company_abc,company_xyz|category:electronics,music');
```

#### Méthode `make()` - Builder fluent

La méthode `make()` permet une construction fluide et lisible :

```php
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->with('status', 'active');
// Résultat: type:user|role:doctor|status:active
```

#### Méthode `fromPairs()`

```php
$cluster = ClusterVO::fromPairs([
    'type' => 'user',
    'role' => 'doctor',
    'status' => 'active',
]);
// Résultat: type:user|role:doctor|status:active

// Avec valeurs multiples
$cluster = ClusterVO::fromPairs([
    'type' => 'user',
    'role' => ['doctor', 'admin'],
    'status' => 'active',
]);
// Résultat: type:user|role:doctor,admin|status:active
```

---

### Lire un Cluster

#### `get(string $key): array`

Retourne toutes les valeurs pour une clé donnée.

```php
$cluster = new ClusterVO('tenant:company_abc,company_xyz|env:production');

$cluster->get('tenant');  // ['company_abc', 'company_xyz']
$cluster->get('env');     // ['production']
$cluster->get('unknown'); // []
```

#### `getFirst(string $key): ?string`

Retourne la première valeur pour une clé.

```php
$cluster = new ClusterVO('role:doctor,admin');

$cluster->getFirst('role'); // 'doctor'
$cluster->getFirst('unknown'); // null
```

#### `has(string $key): bool`

Vérifie si une clé existe.

```php
$cluster = new ClusterVO('tenant:company_abc|env:production');

$cluster->has('tenant');  // true
$cluster->has('unknown'); // false
```

#### `contains(string $key, string $value): bool`

Vérifie si une clé contient une valeur spécifique.

```php
$cluster = new ClusterVO('role:doctor,admin');

$cluster->contains('role', 'doctor');  // true
$cluster->contains('role', 'admin');   // true
$cluster->contains('role', 'unknown'); // false
```

#### `all(): array`

Retourne toutes les paires clé-valeur.

```php
$cluster = new ClusterVO('type:user|role:doctor,admin|status:active');

$cluster->all();
// [
//     'type' => ['user'],
//     'role' => ['doctor', 'admin'],
//     'status' => ['active'],
// ]
```

---

### Ajouter des valeurs

#### `with(string $key, string $value): self`

Ajoute une valeur à une clé (ou crée la clé).

```php
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->with('status', 'active');
// type:user|role:doctor|status:active

// Ajout à une clé existante (les valeurs ne sont pas dupliquées)
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->with('role', 'admin');
// type:user|role:doctor,admin
```

#### `withIf(bool $condition, string $key, string $value): self`

Ajoute une valeur uniquement si la condition est vraie.

```php
$cluster = ClusterVO::make('type', 'user')
    ->withIf($isAdmin, 'role', 'admin')
    ->withIf($isActive, 'status', 'active');
```

#### `withMany(string $key, array $values): self`

Ajoute plusieurs valeurs à une clé.

```php
$cluster = ClusterVO::make('type', 'user')
    ->withMany('role', ['doctor', 'admin', 'manager']);
// type:user|role:doctor,admin,manager
```

#### `withManyIf(bool $condition, string $key, array $values): self`

Ajoute plusieurs valeurs uniquement si la condition est vraie.

```php
$cluster = ClusterVO::make('type', 'user')
    ->withManyIf($hasMultipleRoles, 'role', ['doctor', 'admin']);
```

#### `withDefault(string $key, mixed $value, string $default): self`

Ajoute une valeur avec une valeur par défaut si la valeur est nulle ou vide.

```php
// Avec une valeur
$cluster = ClusterVO::make('type', 'user')
    ->withDefault('status', 'active', 'pending');
// type:user|status:active

// Avec une valeur par défaut
$cluster = ClusterVO::make('type', 'user')
    ->withDefault('status', null, 'pending');
// type:user|status:pending

// false est considéré comme une valeur valide
$cluster = ClusterVO::make('type', 'user')
    ->withDefault('active', false, 'true');
// type:user|active:false

// 0 est considéré comme une valeur valide
$cluster = ClusterVO::make('type', 'user')
    ->withDefault('count', 0, '1');
// type:user|count:0
```

#### `withTernary(string $key, bool $condition, string $trueValue, string $falseValue): self`

Ajoute une paire clé-valeur en fonction d'un booléen.

```php
$cluster = ClusterVO::make('type', 'user')
    ->withTernary('status', $isActive, 'active', 'inactive')
    ->withTernary('verified', $isVerified, 'true', 'false');
// status:active|verified:true (si les conditions sont vraies)
```

---

### Méthodes conditionnelles

Ces méthodes ajoutent des paires uniquement si certaines conditions sont remplies.

#### `whenNotEmpty(string $key, mixed $value): self`

Ajoute une valeur si elle n'est pas vide.

```php
$cluster = ClusterVO::make('type', 'user')
    ->whenNotEmpty('city', $user->city)
    ->whenNotEmpty('country', $user->country)
    ->whenNotEmpty('role', $user->role);
// type:user|city:Paris|country:France|role:doctor

// false est considéré comme une valeur valide
$cluster = ClusterVO::make('type', 'user')
    ->whenNotEmpty('active', false);
// type:user|active:false

// 0 est considéré comme une valeur valide
$cluster = ClusterVO::make('type', 'user')
    ->whenNotEmpty('count', 0);
// type:user|count:0
```

#### `whenNotNull(string $key, mixed $value): self`

Ajoute une valeur si elle n'est pas nulle.

```php
$cluster = ClusterVO::make('type', 'user')
    ->whenNotNull('role', $user->role)
    ->whenNotNull('tenant', $user->tenant_id);
// type:user|role:doctor|tenant:company_abc
```

#### `whenKeyExists(string $key, array $array, string $arrayKey): self`

Ajoute une valeur si une clé existe dans un tableau et n'est pas vide.

```php
$metadata = ['role' => 'doctor', 'tenant' => 'company_abc'];

$cluster = ClusterVO::make('type', 'user')
    ->whenKeyExists('role', $metadata, 'role')     // ✅ 'doctor'
    ->whenKeyExists('tenant', $metadata, 'tenant') // ✅ 'company_abc'
    ->whenKeyExists('unknown', $metadata, 'unknown'); // ❌ Ignoré
// type:user|role:doctor|tenant:company_abc
```

#### `whenArrayNotEmpty(string $key, array $values, string $separator = ','): self`

Ajoute une valeur si le tableau n'est pas vide.

```php
$tags = ['php', 'laravel', 'react'];

$cluster = ClusterVO::make('type', 'user')
    ->whenArrayNotEmpty('tags', $tags);
// type:user|tags:php,laravel,react

// Avec séparateur personnalisé
$cluster = ClusterVO::make('type', 'user')
    ->whenArrayNotEmpty('tags', $tags, ';');
// type:user|tags:php;laravel;react
```

#### `whenNumeric(string $key, mixed $value): self`

Ajoute une valeur si elle est numérique.

```php
$cluster = ClusterVO::make('type', 'user')
    ->whenNumeric('age', 25)           // ✅ 25
    ->whenNumeric('age', '30')         // ✅ '30'
    ->whenNumeric('score', null)       // ❌ null
    ->whenNumeric('rating', 'not a number'); // ❌ Non numérique
// type:user|age:25|age:30
```

#### `whenBool(string $key, mixed $value): self`

Ajoute une valeur si elle est booléenne.

```php
$cluster = ClusterVO::make('type', 'user')
    ->whenBool('verified', true)   // ✅ true → 'true'
    ->whenBool('active', false)    // ✅ false → 'false'
    ->whenBool('role', 'doctor');  // ❌ Non booléen
// type:user|verified:true|active:false
```

---

### Supprimer des valeurs

#### `without(string $key, ?string $value = null): self`

Supprime une valeur ou toute une clé.

```php
$cluster = new ClusterVO('type:user|role:doctor,admin|status:active');

// Supprime une valeur spécifique
$new = $cluster->without('role', 'admin');
// type:user|role:doctor|status:active

// Supprime toute la clé
$new = $cluster->without('role');
// type:user|status:active

// Si la clé n'existe pas, retourne l'instance inchangée
$new = $cluster->without('unknown');
// type:user|role:doctor,admin|status:active (inchangé)
```

---

### Vérification

#### `hasAll(array $keys): bool`

Vérifie si toutes les clés existent.

```php
$cluster = new ClusterVO('type:user|role:doctor|status:active');

$cluster->hasAll(['type', 'role']);       // true
$cluster->hasAll(['type', 'role', 'status']); // true
$cluster->hasAll(['type', 'unknown']);     // false
```

#### `hasAny(array $keys): bool`

Vérifie si au moins une des clés existe.

```php
$cluster = new ClusterVO('type:user|role:doctor');

$cluster->hasAny(['type', 'unknown']);  // true
$cluster->hasAny(['unknown', 'type']);  // true
$cluster->hasAny(['unknown1', 'unknown2']); // false
```

---

### Méthodes statiques

#### `make(string $key, string $value): self`

Crée un cluster avec une paire clé-valeur initiale.

```php
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->with('status', 'active');
```

#### `fromPairs(array $pairs): self`

Crée un cluster à partir d'un tableau de paires.

```php
$cluster = ClusterVO::fromPairs([
    'type' => 'user',
    'role' => ['doctor', 'admin'],
    'status' => 'active',
]);
// type:user|role:doctor,admin|status:active
```

---

### Conversion

#### `getValue(): string`

Retourne la chaîne brute du cluster.

```php
$cluster = new ClusterVO('type:user|role:doctor');
$cluster->getValue(); // 'type:user|role:doctor'
```

#### `__toString(): string`

Alias de `getValue()`.

```php
$cluster = new ClusterVO('type:user|role:doctor');
echo $cluster; // 'type:user|role:doctor'
```

#### `toArray(): array`

Retourne le cluster sous forme de tableau.

```php
$cluster = new ClusterVO('type:user|role:doctor,admin');
$cluster->toArray();
// ['type' => ['user'], 'role' => ['doctor', 'admin']]
```

---

### Exemples avancés

#### Exemple 1: Modèle avec métadonnées

```php
public function getIndexableCluster(): ClusterVO
{
    return ClusterVO::make('type', 'user')
        ->withTernary('status', (bool) $this->is_active, 'active', 'inactive')
        ->withTernary('verified', (bool) $this->email_verified_at, 'true', 'false')
        ->whenNotEmpty('role', $this->role)
        ->whenKeyExists('tenant', $this->metadata ?? [], 'tenant')
        ->whenKeyExists('region', $this->metadata ?? [], 'region')
        ->whenArrayNotEmpty('tags', $this->tags ?? []);
}
// type:user|status:active|verified:true|role:admin|tenant:company_abc|tags:php,laravel
```

#### Exemple 2: Modèle multi-tenant

```php
public function getIndexableCluster(): ClusterVO
{
    $cluster = ClusterVO::make('type', 'doctor')
        ->with('tenant', $this->tenant_id)
        ->withTernary('status', $this->is_available, 'available', 'unavailable')
        ->whenNotEmpty('specialty', $this->specialty)
        ->whenNotEmpty('city', $this->city)
        ->whenNumeric('experience', $this->years_of_experience);

    if ($this->is_featured) {
        $cluster = $cluster->with('featured', 'true');
    }

    return $cluster;
}
// tenant:company_abc|status:available|specialty:cardiology|city:Paris|experience:10|featured:true
```

#### Exemple 3: Construction dynamique avec validation

```php
public function getIndexableCluster(): ClusterVO
{
    return ClusterVO::make('type', 'hospital')
        ->withTernary('status', $this->is_active, 'active', 'inactive')
        ->whenNotEmpty('city', $this->city)
        ->whenNotEmpty('country', $this->country)
        ->withIf($this->hasEmergencyService(), 'emergency', 'true')
        ->withIf($this->hasMaternity(), 'maternity', 'true')
        ->whenNumeric('capacity', $this->capacity);
}
```

#### Exemple 4: Recherche avec cluster multiple

```php
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

// Recherche des médecins actifs en cardiologie à Paris
$cluster = ClusterVO::fromPairs([
    'type' => 'doctor',
    'status' => 'active',
    'specialty' => 'cardiology',
    'city' => 'Paris',
]);

$query = new SearchQueryRecord(
    query: new SearchQueryVO('cardiologue=name,specialty'),
    cluster: $cluster
);

$results = $this->indexer->search($query);
```

---

## Indexer des données

### Indexer un document

```php
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;

class UserService
{
    public function __construct(
        private IndexerInterface $indexer
    ) {}

    public function indexUser(User $user): void
    {
        $record = IndexableRecordFactory::convert($user);
        $this->indexer->index($record);
    }
}
```

### Indexer en masse

```php
use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;

public function indexAllUsers(): void
{
    $records = new IndexableRecordCollection;

    foreach (User::where('is_active', true)->cursor() as $user) {
        $records->add(IndexableRecordFactory::convert($user));
    }

    $this->indexer->indexMany($records);
}
```

### Rafraîchir (mise à jour)

```php
public function updateUser(User $user): void
{
    $user->save();
    $record = IndexableRecordFactory::convert($user);
    $this->indexer->refresh($record);
}
```

---

## GenericIndexerService

Service générique d'indexation qui fonctionne avec n'importe quel modèle Eloquent implémentant `Indexable`. Il gère automatiquement le chunking, le batch processing, la limitation et les opérations CRUD sur l'index.

### Injection du service

```php
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;

class UserIndexer
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer,
    ) {}
}
```

### Indexer un document

```php
// Avec le modèle directement
$user = User::find($userId);
$this->genericIndexer->index($user);

// Ou avec la classe et l'ID
$this->genericIndexer->indexById(User::class, $userId);
```

### Indexer tous les documents

```php
$this->genericIndexer->indexAll(User::class);
```

### Indexer avec batch et limite

```php
$this->genericIndexer
    ->setBatchSize(50)
    ->setLimit(1000)
    ->indexAll(Doctor::class);
```

### Reconstruire tout l'index

```php
$this->genericIndexer->reindexAll(User::class);
```

### Supprimer un document

```php
// Avec le modèle directement
$user = User::find($userId);
$this->genericIndexer->delete($user);

// Ou avec la classe et l'ID
$this->genericIndexer->deleteById(User::class, $userId);
```

### Supprimer tous les documents d'un type

```php
$this->genericIndexer->deleteAll(User::class);
```

### Rafraîchir un document

```php
// Avec le modèle directement
$user = User::find($userId);
$this->genericIndexer->refresh($user);

// Ou avec la classe et l'ID
$this->genericIndexer->refreshById(User::class, $userId);
```

### Compter les documents indexés

```php
$count = $this->genericIndexer->countIndexed(User::class);
```

### Vérifier l'existence

```php
// Avec le modèle directement
$user = User::find($userId);
if ($this->genericIndexer->exists($user)) {
    // L'utilisateur est indexé
}

// Ou avec la classe et l'ID
if ($this->genericIndexer->existsById(User::class, $userId)) {
    // L'utilisateur est indexé
}
```

### Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;

class DoctorIndexer
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer,
    ) {}

    public function indexDoctor(int $doctorId): void
    {
        $doctor = Doctor::find($doctorId);
        if ($doctor) {
            $this->genericIndexer->index($doctor);
        }
    }

    public function reindexAllDoctors(): void
    {
        $this->genericIndexer
            ->setBatchSize(50)
            ->setLimit(10000)
            ->reindexAll(Doctor::class);
    }

    public function getIndexedDoctorCount(): int
    {
        return $this->genericIndexer->countIndexed(Doctor::class);
    }

    public function cleanupDoctorIndex(): void
    {
        $this->genericIndexer->deleteAll(Doctor::class);
    }
}
```

---

## GenericIndexModelsDirective

Directive CLI pour indexer les modèles configurés dans `indexer.model_indexables`.

### Signature

```bash
index:models {batch=50} {limit=?} {models*} {--reindex} {--count} {--delete}
```

### Options

| Option | Description |
|--------|-------------|
| `batch` | Taille des lots pour le chunking (défaut: 50) |
| `limit` | Nombre maximum d'éléments à indexer (optionnel) |
| `models*` | Liste des modèles à indexer (notation pointée: `App.Models.User`) |
| `--reindex` | Supprime puis réindexe tous les modèles |
| `--count` | Compte les documents indexés |
| `--delete` | Supprime tous les documents de l'index |

### Exemples

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

# Ignorer le batch (valeur par défaut) et appliquer une limite
./bin/directive index:models _ 20 [App.Models.User]
```

### Configuration

```php
// config/indexer.php
'model_indexables' => [
    App\Models\User::class,
    App\Models\Hospital::class,
    App\Models\Specialty::class,
],
```

---

## GenericOrchestratorRecurringTask

Tâche récurrente qui orchestre l'indexation de tous les modèles configurés. Elle récupère la liste des modèles depuis `model_indexables` et dispatch des tâches batch pour chaque lot.

### Configuration

```php
// config/indexer.php
'batch_size' => 50,
'model_indexables' => [
    App\Models\User::class,
    App\Models\Hospital::class,
],
```

### Fonctionnement

1. Récupère les modèles configurables depuis `model_indexables`
2. Pour chaque modèle, récupère les IDs éligibles (`shouldBeIndexed()`)
3. Découpe les IDs par lots (`batch_size`)
4. Enregistre une tâche `GenericIndexBatchUniqueTask` pour chaque lot

### Enregistrement

```php
use AndyDefer\LaravelIndexer\Tasks\RecurringTasks\GenericOrchestratorRecurringTask;

// La tâche est automatiquement enregistrée par le provider
```

---

## GenericIndexBatchUniqueTask

Tâche unique qui indexe un lot d'éléments pour plusieurs modèles.

### Payload

```json
{
    "items": [
        {"modelClass": "App\\Models\\User", "id": 1},
        {"modelClass": "App\\Models\\User", "id": 2},
        {"modelClass": "App\\Models\\Hospital", "id": 3}
    ]
}
```

### Fonctionnement

1. Reçoit une `IndexableVOCollection` contenant des paires `(modelClass, id)`
2. Récupère toutes les instances en **UNE SEULE requête par classe**
3. Pour chaque modèle, vérifie l'éligibilité (`shouldBeIndexed()`)
4. Supprime le document s'il existe déjà
5. Indexe le modèle avec son cluster dynamique (`getIndexableCluster()`)

### Utilisation manuelle

```php
use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;

$collection = new IndexableVOCollection;
$collection->add(new IndexableVO(User::class, 1));
$collection->add(new IndexableVO(User::class, 2));
$collection->add(new IndexableVO(Hospital::class, 3));

$payload = StrictDataObject::from([
    'items' => $collection,
]);

$config = UniqueTaskConfigRecord::from([
    'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
    'max_attempts' => new MaxFailedAttemptsVO(3),
    'grace_period' => new DurationVO(3600),
    'description' => new DescriptionVO('Batch task for indexing'),
]);

$uniqueTaskService->register(
    new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
    $payload,
    $config
);
```

---

## Rechercher

### Comment fonctionne la recherche ?

1. Le terme est normalisé (minuscules, accents supprimés)
2. Le système génère tous les n-grammes possibles du terme
3. Il recherche les tokens LEXICAL correspondants
4. Si aucun résultat, il recherche les tokens METAPHONE (phonétique)
5. Retourne les documents trouvés

**Exemple :**
- Indexé : "john" → tokens : ["joh", "ohn", "john"]
- Recherche "joh" → trouve "john" car "joh" est un token
- Recherche "jon" → trouve "john" via métaphone (JN → jn)

### Recherche simple

```php
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

public function searchUsers(string $query): array
{
    $searchQuery = new SearchQueryRecord(
        query: new SearchQueryVO($query . '=name,email,bio')
    );

    $results = $this->indexer->search($searchQuery);
    
    $userIds = $results->getItems()->getIdValues()->toArray();
    
    return User::whereIn('id', $userIds)->get();
}
```

### Recherche multi-termes (AND)

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|developer=bio')
);
```

### Recherche multi-champs (OR)

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email,bio')
);
```

### Recherche avec limite

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    limit: 20
);
```

### Filtrer par cluster

```php
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

$cluster = ClusterVO::make('tenant', 'company_abc')
    ->with('type', 'user')
    ->with('status', 'active');

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    cluster: $cluster
);

$results = $this->indexer->search($query);
```

### Filtrer par cluster avec `fromPairs()`

```php
$cluster = ClusterVO::fromPairs([
    'tenant' => 'company_abc',
    'type' => 'user',
    'status' => ['active', 'pending'],
    'role' => 'doctor',
]);

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    cluster: $cluster
);
```

### Vérifier l'existence d'un document

```php
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$exists = $this->indexer->exists($fingerPrint);
```

---

## Autocomplétion

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;

class AutocompleteService
{
    public function __construct(
        private IndexedTokenRepository $tokenRepository
    ) {}

    public function suggest(string $prefix): array
    {
        $tokens = $this->tokenRepository->autocomplete($prefix, 10);
        return $tokens->pluck('token')->toArray();
    }
}
```

### Autocomplétion par champ

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

### Autocomplétion avec cluster

```php
$cluster = ClusterVO::make('tenant', 'company_abc');

$tokens = $this->tokenRepository->getModel()
    ->newQuery()
    ->where('token', 'LIKE', $prefix . '%')
    ->whereHas('document', function ($q) use ($cluster) {
        $q->where('cluster', 'LIKE', '%' . $cluster->getValue() . '%');
    })
    ->select('token')
    ->distinct()
    ->limit(10)
    ->get();
```

---

## Supprimer

### Supprimer un document

```php
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$this->indexer->delete($fingerPrint);
```

### Supprimer plusieurs

```php
use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;

$collection = new IndexableFingerPrintVOCollection;
$collection->add(new IndexableFingerPrintVO('App.Models.User|123'));
$collection->add(new IndexableFingerPrintVO('App.Models.User|456'));
$this->indexer->deleteMany($collection);
```

### Supprimer par namespace

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;

$repository = app(IndexedDocumentRepository::class);
$repository->deleteByNamespace('App.Models.User');
```

### Supprimer par cluster

```php
$cluster = ClusterVO::make('tenant', 'company_abc');
$repository->deleteByCluster($cluster);

$repository->deleteByClusterKeyValue('tenant', 'company_abc');
```

### Vider l'index

```php
$this->indexer->clear();
```

---

## Repositories

### IndexedDocumentRepository

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;

$repository = app(IndexedDocumentRepository::class);

// Trouver
$doc = $repository->findByFingerPrint($fingerPrint);
$doc = $repository->findByFingerprintString('App.Models.User|123');
$docs = $repository->findByNamespace('App.Models.User');
$docs = $repository->findByCluster($cluster);
$docs = $repository->findByClusterKeyValue('tenant', 'company_abc');
$docs = $repository->findByIds(['uuid1', 'uuid2']);

// Compter
$count = $repository->countByNamespace('App.Models.User');
$count = $repository->countByCluster($cluster);

// Distinct
$namespaces = $repository->getDistinctNamespaces();
$keys = $repository->getDistinctClusterKeys();
$values = $repository->getDistinctClusterValues('tenant');

// Vérifier
$exists = $repository->existsByFingerPrint($fingerPrint);
$exists = $repository->existsByNamespace('App.Models.User');
$exists = $repository->existsByCluster($cluster);

// Supprimer
$repository->deleteByFingerPrint($fingerPrint);
$repository->deleteByFingerprintString('App.Models.User|123');
$repository->deleteByNamespace('App.Models.User');
$repository->deleteByCluster($cluster);
$repository->deleteByClusterKeyValue('tenant', 'company_abc');
```

### IndexedTokenRepository

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Enums\GramType;

$repository = app(IndexedTokenRepository::class);

// Trouver
$tokens = $repository->findByToken('john');
$tokens = $repository->findByType(GramType::LEXICAL);
$tokens = $repository->findByField('name');
$tokens = $repository->findByDocumentId('uuid');
$tokens = $repository->findByDocumentFingerPrint($fingerPrint);
$tokens = $repository->findByNamespace('App.Models.User');
$tokens = $repository->findByCluster($cluster);
$tokens = $repository->findByClusterKeyValue('tenant', 'company_abc');

// Token + critères
$tokens = $repository->findByTokenAndField('john', 'name');
$tokens = $repository->findByTokenAndType('john', GramType::LEXICAL);
$tokens = $repository->findByTokenAndNamespace('john', 'App.Models.User');
$tokens = $repository->findByTokenAndCluster('john', $cluster);
$tokens = $repository->findByTokenFieldAndNamespace('john', 'name', 'App.Models.User');

// Document IDs par token
$ids = $repository->getDocumentIdsForToken('john');
$ids = $repository->getDocumentIdsForTokenAndField('john', 'name');
$ids = $repository->getDocumentIdsForTokenAndCluster('john', $cluster);
$ids = $repository->getDocumentIdsForTokenFieldAndCluster('john', 'name', $cluster);

// Compter
$count = $repository->countDistinctTokens();
$count = $repository->countByType(GramType::LEXICAL);
$count = $repository->countByField('name');
$count = $repository->countByNamespace('App.Models.User');

// Supprimer
$repository->deleteByDocumentId('uuid');
$repository->deleteByDocumentFingerPrint($fingerPrint);
$repository->deleteByNamespace('App.Models.User');
$repository->deleteByCluster($cluster);
$repository->deleteByClusterKeyValue('tenant', 'company_abc');
$repository->deleteByToken('john');
$repository->deleteByTokenAndField('john', 'name');
```

---

## Collections

### IndexableSearchResultCollection

```php
$results = $this->indexer->search($query);

foreach ($results as $result) {
    $item = $result->item;
    $fingerprint = $item->fingerprint->getValue();
    $field = $result->field;
    $gram = $result->gram_value;
    $type = $result->gram_type->value;
}

// Filtrage
$byField = $results->filterByField('name');
$byNamespace = $results->filterByNamespace('App.Models.User');

// Extraction
$ids = $results->getIds();
$items = $results->getItems();
$fingerPrints = $results->getFingerPrints();

// Groupement
$byField = $results->groupByField();
$byNamespace = $results->groupByNamespace();
```

### IndexableRecordCollection

```php
$records = new IndexableRecordCollection;

$records->add($record);
$chunks = $records->chunk(100);

$users = $records->filterByNamespace('App.Models.User');
$withTenant = $records->filterByCluster('tenant', 'company_abc');

$fingerPrints = $records->getFingerPrints();
$ids = $records->getIdValues();

$record = $records->findById('123');
$record = $records->findByIdAndNamespace('123', 'App.Models.User');

$hasId = $records->containsId('123');
$hasNamespace = $records->containsNamespace('App.Models.User');

$this->indexer->indexMany($records);
```

### IndexableVOCollection

Collection spécialisée pour manipuler les `IndexableVO`.

```php
use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;

$collection = new IndexableVOCollection;
$collection->add(new IndexableVO(User::class, 1));
$collection->add(new IndexableVO(User::class, 2));
$collection->add(new IndexableVO(Hospital::class, 3));

// Récupérer les IDs
$ids = $collection->getIds(); // [1, 2, 3]

// Récupérer les classes de modèles
$classes = $collection->getModelClasses(); // [User::class, User::class, Hospital::class]

// Récupérer les instances en UNE SEULE requête par classe
$instances = $collection->getModelInstances();

// Filtrer par classe
$users = $collection->filterByModelClass(User::class);

// Filtrer par plusieurs classes
$usersAndHospitals = $collection->filterByModelClasses([User::class, Hospital::class]);

// Filtrer par classe et IDs
$specificUsers = $collection->filterByModelClassAndIds(User::class, [1, 2]);

// Vérification
$hasId = $collection->containsId(1);
$hasClass = $collection->containsModelClass(User::class);

// Recherche
$item = $collection->findById(1);

// Groupement par classe
$groups = $collection->groupByModelClass();
// [
//     User::class => IndexableVOCollection [items: 2],
//     Hospital::class => IndexableVOCollection [items: 1],
// ]
```

### IndexableFingerPrintVOCollection

```php
$fingerPrints = new IndexableFingerPrintVOCollection;

$users = $fingerPrints->filterByNamespace('App.Models.User');
$ids = $fingerPrints->getIds();
$namespaces = $fingerPrints->getNamespaces();

$hasId = $fingerPrints->containsId('123');
$hasNamespace = $fingerPrints->containsNamespace('App.Models.User');

$fp = $fingerPrints->findByValue('App.Models.User|123');
$fp = $fingerPrints->findByIdAndNamespace('123', 'App.Models.User');

$grouped = $fingerPrints->groupByNamespace();
```

### ClusterVOCollection

```php
$clusters = new ClusterVOCollection;

$withTenant = $clusters->filterByKey('tenant');
$withSpecific = $clusters->filterByPair('tenant', 'company_abc');

$values = $clusters->getValuesForKey('tenant');
$keys = $clusters->getUniqueKeys();

$grouped = $clusters->groupByKey('tenant');

$hasKey = $clusters->hasKey('tenant');
$hasPair = $clusters->hasPair('tenant', 'company_abc');

$merged = $clusters->mergeAll();
```

---

## License

MIT © [Andy Defer](https://github.com/andydefer)