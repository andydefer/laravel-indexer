# Laravel Indexer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)
[![PHP Version Require](https://img.shields.io/packagist/php-v/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2F11%2F12%2F13%2F14%2F15-ff2d20.svg)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)

## Table des matières

- [Installation](#installation)
- [Préparer votre modèle](#préparer-votre-modèle)
- [Les Clusters](#les-clusters)
  - [Format du cluster](#format-du-cluster)
  - [Créer un Cluster](#créer-un-cluster)
  - [Lire un Cluster](#lire-un-cluster)
  - [Ajouter des valeurs](#ajouter-des-valeurs)
  - [Méthodes conditionnelles](#méthodes-conditionnelles)
  - [Méthodes utilitaires pour tableaux et enums](#méthodes-utilitaires-pour-tableaux-et-enums)
  - [Mode de recherche (AND / OR / NOT)](#mode-de-recherche-and--or--not)
  - [Collection de clusters](#collection-de-clusters-clustervocollection)
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
    'full_text_max_length' => 100,
    'max_text_length' => 1000,
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
        return new ClusterVO('type:user')
            ->withTernary('status', (bool) $this->is_active, 'active', 'inactive')
            ->whenNotEmpty('role', $this->role)
            ->whenBool('verified', $this->email_verified_at !== null);
    }
}
```

---

## Les Clusters

Le cluster est un **filtre contextuel** permettant de filtrer les recherches par contexte (tenant, environnement, rôle, statut, etc.). C'est un élément clé de l'indexation qui permet une recherche multi-contextes.

### Format du cluster

```
key1:value1|key2:value2|key3:value3
```

| Élément | Description |
|---------|-------------|
| `key:value` | Paire clé-valeur (une clé = une valeur) |
| `|` | Séparateur de paires |
| `@AND` / `@OR` / `@NOT` | Mode de recherche (optionnel pour le stockage, obligatoire pour la recherche) |

**Caractères autorisés :**
- **Clés** : `a-z`, `A-Z`, `0-9`, `_` uniquement
- **Valeurs** : Tous les caractères (libre)

### Créer un Cluster

#### Constructeur classique

```php
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

// Cluster simple (stockage)
$cluster = new ClusterVO('tenant:company_abc');

// Cluster multi-attributs (stockage)
$cluster = new ClusterVO('tenant:company_abc|env:production|region:europe');

// Cluster avec mode (recherche)
$cluster = new ClusterVO('tenant:company_abc|env:production@AND');
$cluster = new ClusterVO('role_doctor:true|role_admin:true@OR');
$cluster = new ClusterVO('status:inactive@NOT');
```

#### Méthode `make()` - Builder fluent

```php
// Stockage (sans mode)
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->with('status', 'active');
// Résultat: type:user|role:doctor|status:active

// Recherche (avec mode)
$cluster = ClusterVO::make('type', 'user', 'AND')
    ->with('role', 'doctor')
    ->with('status', 'active');
// Résultat: type:user|role:doctor|status:active@AND
```

---

### Lire un Cluster

#### `get(string $key): ?string`

Retourne la valeur pour une clé donnée.

```php
$cluster = new ClusterVO('tenant:company_abc|env:production');

$cluster->get('tenant');  // 'company_abc'
$cluster->get('env');     // 'production'
$cluster->get('unknown'); // null
```

#### `has(string $key): bool`

Vérifie si une clé existe.

```php
$cluster = new ClusterVO('tenant:company_abc|env:production');

$cluster->has('tenant');  // true
$cluster->has('unknown'); // false
```

#### `all(): array`

Retourne toutes les paires clé-valeur.

```php
$cluster = new ClusterVO('type:user|role:doctor|status:active');

$cluster->all();
// [
//     'type' => 'user',
//     'role' => 'doctor',
//     'status' => 'active',
// ]
```

---

### Ajouter des valeurs

#### `with(string $key, string $value): self`

Ajoute une paire clé-valeur.

```php
$cluster = new ClusterVO('type:user')
    ->with('role', 'doctor')
    ->with('status', 'active');
// type:user|role:doctor|status:active
```

#### `withIf(bool $condition, string $key, string $value): self`

Ajoute une paire uniquement si la condition est vraie.

```php
$cluster = new ClusterVO('type:user')
    ->withIf($isAdmin, 'role', 'admin')
    ->withIf($isActive, 'status', 'active');
```

#### `withTernary(string $key, bool $condition, string $trueValue, string $falseValue): self`

Ajoute une paire clé-valeur en fonction d'un booléen.

```php
$cluster = new ClusterVO('type:user')
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
$cluster = new ClusterVO('type:user')
    ->whenNotEmpty('city', $user->city)
    ->whenNotEmpty('country', $user->country)
    ->whenNotEmpty('role', $user->role);
// type:user|city:Paris|country:France|role:doctor
```

#### `whenNotNull(string $key, mixed $value): self`

Ajoute une valeur si elle n'est pas nulle.

```php
$cluster = new ClusterVO('type:user')
    ->whenNotNull('role', $user->role)
    ->whenNotNull('tenant', $user->tenant_id);
// type:user|role:doctor|tenant:company_abc
```

#### `whenNumeric(string $key, mixed $value): self`

Ajoute une valeur si elle est numérique.

```php
$cluster = new ClusterVO('type:user')
    ->whenNumeric('age', 25)           // ✅ 25
    ->whenNumeric('age', '30')         // ✅ '30'
    ->whenNumeric('score', null)       // ❌ null
    ->whenNumeric('rating', 'not a number'); // ❌ Non numérique
// type:user|age:25|age:30
```

#### `whenBool(string $key, mixed $value): self`

Ajoute une valeur si elle est booléenne.

```php
$cluster = new ClusterVO('type:user')
    ->whenBool('verified', true)   // ✅ true → 'true'
    ->whenBool('active', false)    // ✅ false → 'false'
    ->whenBool('role', 'doctor');  // ❌ Non booléen
// type:user|verified:true|active:false
```

---

### Méthodes utilitaires pour tableaux et enums

Ces méthodes permettent d'ajouter facilement plusieurs paires à partir de tableaux ou d'enums.

#### `withCases(string $prefix, array $values, string $suffix = ''): self`

Ajoute des paires pour chaque valeur d'un tableau. Format : `prefix_{value}{suffix}:true`

```php
$languages = ['fr', 'en', 'lu', 'ln'];
$cluster = new ClusterVO('type:user')
    ->withCases('lang_', $languages);
// type:user|lang_fr:true|lang_en:true|lang_lu:true|lang_ln:true
```

#### `withEnum(string $prefix, string $enumClass, string $suffix = ''): self`

Ajoute des paires pour chaque case d'un enum `UnitEnum`. Utilise le nom du case en minuscules.

```php
$cluster = new ClusterVO('type:user')
    ->withEnum('role_', UserType::class);
// type:user|role_patient:true|role_doctor:true|role_admin:true|role_staff:true
```

#### `withEnumValues(string $prefix, string $enumClass, string $suffix = ''): self`

Ajoute des paires pour les valeurs d'un enum. Pour `BackedEnum`, utilise `$case->value`. Pour `UnitEnum`, utilise `$case->name`. Tout est converti en minuscules.

```php
$cluster = new ClusterVO('type:user')
    ->withEnumValues('status_', UserStatus::class);
// type:user|status_active:true|status_inactive:true|status_pending:true|status_banned:true
```

---

### Mode de recherche (AND / OR / NOT)

Le mode est utilisé UNIQUEMENT pour la recherche. Il est optionnel pour le stockage.

#### Définition du mode

```php
// Sans mode (stockage)
$cluster = new ClusterVO('type:user|status:active');

// Avec mode (recherche)
$cluster = new ClusterVO('type:user|status:active@AND');
$cluster = new ClusterVO('type:user|status:active@OR');
$cluster = new ClusterVO('status:inactive@NOT');
```

#### Utilisation en recherche

```php
// Mode AND : toutes les conditions doivent être remplies
$cluster = new ClusterVO('type:user|status:active@AND');
// WHERE cluster LIKE '%type:user%' AND cluster LIKE '%status:active%'

// Mode OR : au moins une condition doit être remplie
$cluster = new ClusterVO('role_doctor:true|role_admin:true@OR');
// WHERE cluster LIKE '%role_doctor:true%' OR cluster LIKE '%role_admin:true%'

// Mode NOT : aucune condition ne doit être remplie
$cluster = new ClusterVO('status:inactive@NOT');
// WHERE cluster NOT LIKE '%status:inactive%'
```

#### Application à une requête

```php
$cluster = new ClusterVO('type:user|status:active@AND');
$cluster->applyToQuery($query);
// Ou avec une colonne personnalisée
$cluster->applyToQuery($query, 'cluster');
```

**⚠️ Important :** `applyToQuery()` lève une exception si le cluster n'a pas de mode.

---

### Collection de clusters (ClusterVOCollection)

La collection de clusters permet de combiner plusieurs clusters avec des opérateurs logiques.

```php
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;

$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

// Opérateur AND : tous les clusters doivent correspondre
$results = $repository->findByClusters($clusters, 'AND');

// Opérateur OR : au moins un cluster doit correspondre
$results = $repository->findByClusters($clusters, 'OR');

// Opérateur NOT : aucun cluster ne doit correspondre
$results = $repository->findByClusters($clusters, 'NOT');
```

**Filtrage avancé :**

```php
// Exemple : (role:doctor OR role:admin) AND status:active
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true|role_admin:true@OR'));
$clusters->add(new ClusterVO('status:active@AND'));

$results = $repository->findByClusters($clusters, 'AND');
```

---

### Supprimer des valeurs

#### `without(string $key): self`

Supprime une clé entière.

```php
$cluster = new ClusterVO('type:user|role:doctor|status:active');

// Supprime la clé
$new = $cluster->without('role');
// type:user|status:active

// Si la clé n'existe pas, retourne l'instance inchangée
$new = $cluster->without('unknown');
// type:user|role:doctor|status:active (inchangé)
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

#### `make(string $key, string $value, ?string $mode = null): self`

Crée un cluster avec une paire clé-valeur initiale.

```php
// Stockage (sans mode)
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->with('status', 'active');

// Recherche (avec mode)
$cluster = ClusterVO::make('type', 'user', 'AND')
    ->with('role', 'doctor')
    ->with('status', 'active');
// type:user|role:doctor|status:active@AND
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
$cluster = new ClusterVO('type:user|role:doctor');
$cluster->toArray();
// ['type' => 'user', 'role' => 'doctor']
```

---

### Exemples avancés

#### Exemple 1: Modèle avec métadonnées

```php
public function getIndexableCluster(): ClusterVO
{
    return new ClusterVO('type:user')
        ->withTernary('status', (bool) $this->is_active, 'active', 'inactive')
        ->withTernary('verified', (bool) $this->email_verified_at, 'true', 'false')
        ->whenNotEmpty('role', $this->role)
        ->whenBool('verified', $this->email_verified_at !== null);
}
// type:user|status:active|verified:true|role:admin
```

#### Exemple 2: Modèle multi-tenant

```php
public function getIndexableCluster(): ClusterVO
{
    $cluster = new ClusterVO('type:doctor')
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

#### Exemple 3: Utilisation des enums

```php
public function getIndexableCluster(): ClusterVO
{
    return new ClusterVO('type:user')
        ->withEnum('role_', UserType::class)
        ->withCases('lang_', $this->languages)
        ->withTernary('status', $this->is_active, 'active', 'inactive');
}
// type:user|role_patient:true|role_doctor:true|lang_fr:true|lang_en:true|status:active
```

#### Exemple 4: Recherche avec cluster multiple (AND)

```php
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;

// Recherche des médecins actifs en cardiologie à Paris
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('type:doctor|specialty:cardiology@AND'));
$clusters->add(new ClusterVO('city:Paris@AND'));
$clusters->add(new ClusterVO('status:active@AND'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('cardiologue=name,specialty'),
    clusters: $clusters,
    clustersOperator: 'AND'
);

$results = $this->indexer->search($query);
```

#### Exemple 5: Recherche OR pour les rôles

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true|role_admin:true@OR'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    clusters: $clusters,
    clustersOperator: 'OR'
);
```

#### Exemple 6: Exclusion (NOT)

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('status:inactive@NOT'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    clusters: $clusters,
    clustersOperator: 'NOT'
);
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

### Recherche simple avec clusters

```php
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;

public function searchUsers(string $query): array
{
    $clusters = new ClusterVOCollection();
    
    $searchQuery = new SearchQueryRecord(
        query: new SearchQueryVO($query . '=name,email,bio'),
        clusters: $clusters,
        clustersOperator: 'AND'
    );

    $results = $this->indexer->search($searchQuery);
    
    $userIds = $results->getItems()->getIdValues()->toArray();
    
    return User::whereIn('id', $userIds)->get();
}
```

### Recherche multi-termes (AND)

```php
$clusters = new ClusterVOCollection();
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|developer=bio'),
    clusters: $clusters,
    clustersOperator: 'AND'
);
```

### Recherche multi-champs (OR)

```php
$clusters = new ClusterVOCollection();
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email,bio'),
    clusters: $clusters,
    clustersOperator: 'AND'
);
```

### Recherche avec limite

```php
$clusters = new ClusterVOCollection();
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    clusters: $clusters,
    clustersOperator: 'AND',
    limit: 20
);
```

### Filtrer par cluster AND

```php
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc|type:user|status:active@AND'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    clusters: $clusters,
    clustersOperator: 'AND'
);

$results = $this->indexer->search($query);
```

### Filtrer par cluster OR

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    clusters: $clusters,
    clustersOperator: 'OR'
);
```

### Filtrer par cluster NOT

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('status:inactive@NOT'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    clusters: $clusters,
    clustersOperator: 'NOT'
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
$cluster = new ClusterVO('tenant:company_abc@AND');

$tokens = $this->tokenRepository->getModel()
    ->newQuery()
    ->where('token', 'LIKE', $prefix . '%')
    ->whereHas('document', function ($q) use ($cluster) {
        $cluster->applyToQuery($q);
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
$cluster = new ClusterVO('tenant:company_abc@AND');
$repository->deleteByCluster($cluster);

$repository->deleteByClusterKeyValue('tenant', 'company_abc');
```

### Supprimer par clusters multiples

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

$deleted = $repository->deleteByClusters($clusters, 'AND');
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
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;

$repository = app(IndexedDocumentRepository::class);

// Trouver
$doc = $repository->findByFingerPrint($fingerPrint);
$doc = $repository->findByFingerprintString('App.Models.User|123');
$docs = $repository->findByNamespace('App.Models.User');
$docs = $repository->findByCluster($cluster);
$docs = $repository->findByClusters($clusters, 'AND');
$docs = $repository->findByClusters($clusters, 'OR');
$docs = $repository->findByClusters($clusters, 'NOT');
$docs = $repository->findByClusterKeyValue('tenant', 'company_abc');
$docs = $repository->findByIds(['uuid1', 'uuid2']);

// Compter
$count = $repository->countByNamespace('App.Models.User');
$count = $repository->countByCluster($cluster);
$count = $repository->countByClusters($clusters, 'AND');

// Distinct
$namespaces = $repository->getDistinctNamespaces();
$keys = $repository->getDistinctClusterKeys();
$values = $repository->getDistinctClusterValues('tenant');

// Vérifier
$exists = $repository->existsByFingerPrint($fingerPrint);
$exists = $repository->existsByNamespace('App.Models.User');
$exists = $repository->existsByCluster($cluster);
$exists = $repository->existsByClusters($clusters, 'AND');

// Supprimer
$repository->deleteByFingerPrint($fingerPrint);
$repository->deleteByFingerprintString('App.Models.User|123');
$repository->deleteByNamespace('App.Models.User');
$repository->deleteByCluster($cluster);
$repository->deleteByClusters($clusters, 'AND');
$repository->deleteByClusterKeyValue('tenant', 'company_abc');
```

### IndexedTokenRepository

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;

$repository = app(IndexedTokenRepository::class);

// Trouver
$tokens = $repository->findByToken('john');
$tokens = $repository->findByType(GramType::LEXICAL);
$tokens = $repository->findByField('name');
$tokens = $repository->findByDocumentId('uuid');
$tokens = $repository->findByDocumentFingerPrint($fingerPrint);
$tokens = $repository->findByNamespace('App.Models.User');
$tokens = $repository->findByCluster($cluster);
$tokens = $repository->findByClusters($clusters, 'AND');
$tokens = $repository->findByClusters($clusters, 'OR');
$tokens = $repository->findByClusters($clusters, 'NOT');
$tokens = $repository->findByClusterKeyValue('tenant', 'company_abc');

// Token + critères
$tokens = $repository->findByTokenAndField('john', 'name');
$tokens = $repository->findByTokenAndType('john', GramType::LEXICAL);
$tokens = $repository->findByTokenAndNamespace('john', 'App.Models.User');
$tokens = $repository->findByTokenAndCluster('john', $cluster);
$tokens = $repository->findByTokenAndClusters('john', $clusters, 'AND');
$tokens = $repository->findByTokenFieldAndNamespace('john', 'name', 'App.Models.User');

// Document IDs par token
$ids = $repository->getDocumentIdsForToken('john');
$ids = $repository->getDocumentIdsForTokenAndField('john', 'name');
$ids = $repository->getDocumentIdsForTokenAndCluster('john', $cluster);
$ids = $repository->getDocumentIdsForTokenAndClusters('john', $clusters, 'AND');
$ids = $repository->getDocumentIdsForTokenFieldAndCluster('john', 'name', $cluster);
$ids = $repository->getDocumentIdsForTokenFieldAndClusters('john', 'name', $clusters, 'AND');

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
$repository->deleteByClusters($clusters, 'AND');
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
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;

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