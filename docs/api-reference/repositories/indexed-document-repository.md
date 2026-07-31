# IndexedDocumentRepository - Référence Technique

## Description

Repository concret pour les opérations de persistance sur les documents indexés (`IndexedDocument`). Implémente `IndexedDocumentRepositoryInterface` et étend `AbstractRepository` pour fournir toutes les opérations CRUD et de recherche avancée, avec un support multi-driver pour l'extraction de données JSON.

## Hiérarchie / Implémentations

```
AbstractRepository<IndexedDocument, IndexedDocumentRecord>
    └── IndexedDocumentRepository
            └── Implémente IndexedDocumentRepositoryInterface
```

## Rôle principal

Ce repository est le point d'accès principal pour toutes les opérations sur les documents indexés. Il fournit :

- **CRUD complet** : Création, lecture, mise à jour, suppression
- **Recherche spécialisée** : Par fingerprint, namespace, cluster query
- **Extraction multi-driver** : Support MySQL, PostgreSQL, SQLite pour les clés/valeurs distinctes de cluster
- **Opérations par lots** : Création multiple, suppression par namespace
- **Filtrage avancé** : Via `applyFilters()` pour les requêtes complexes

## Détails

[Voir la classe IndexedDocumentRepositoryInterface](https://github.com/andydefer/laravel-indexer/blob/main/src/Contracts/Repositories/IndexedDocumentRepositoryInterface.php)

## API / Méthodes publiques

### `__construct()`

Initialise le repository avec le modèle et le record associés.

```php
public function __construct()
{
    parent::__construct(IndexedDocument::class, IndexedDocumentRecord::class);
}
```

---

### `getModel(): Model`

Retourne l'instance du modèle Eloquent.

**Retourne :** `Model` - Instance de `IndexedDocument`

**Exemple :**
```php
$model = $repository->getModel();
$query = $model->newQuery();
```

---

### `findByFingerPrint(IndexableFingerprintVO $fingerprint): ?IndexedDocument`

Recherche un document par son fingerprint (value object).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint à rechercher |

**Retourne :** `IndexedDocument|null` - Document trouvé ou `null`

**Exemple :**
```php
$fingerprint = new IndexableFingerprintVO('App\Models\User|123');
$document = $repository->findByFingerPrint($fingerprint);
```

---

### `findByFingerprintString(string $fingerprint): ?IndexedDocument`

Recherche un document par sa chaîne de fingerprint brute.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `string` | Chaîne de fingerprint |

**Retourne :** `IndexedDocument|null` - Document trouvé ou `null`

---

### `findByNamespace(string $namespace): Collection`

Récupère tous les documents d'un namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à filtrer |

**Retourne :** `Collection<int, IndexedDocument>` - Collection des documents

**Exemple :**
```php
$users = $repository->findByNamespace('App\Models\User');
```

---

### `findByClusterQuery(string $query, string $column = 'cluster', ?DatabaseDriver $driver = null): Collection`

Recherche des documents par requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |
| `$driver` | `DatabaseDriver|null` | Driver de base de données |

**Retourne :** `Collection<int, IndexedDocument>` - Collection des documents correspondants

**Exemples :**
```php
// Recherche simple
$active = $repository->findByClusterQuery('status=active');

// Condition AND
$admins = $repository->findByClusterQuery('status=active & role=admin');

// Fonction SQL
$richUsers = $repository->findByClusterQuery('COUNT(orders) > 5');
```

---

### `findByIds(array $ids): Collection`

Recherche des documents par leurs IDs.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ids` | `string[]` | IDs des documents |

**Retourne :** `Collection<int, IndexedDocument>` - Collection des documents

---

### `deleteByFingerPrint(IndexableFingerprintVO $fingerprint): int`

Supprime un document par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint du document |

**Retourne :** `int` - Nombre de suppressions (0 ou 1)

---

### `deleteByFingerprintString(string $fingerprint): int`

Supprime un document par sa chaîne de fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `string` | Chaîne de fingerprint |

**Retourne :** `int` - Nombre de suppressions (0 ou 1)

---

### `deleteByNamespace(string $namespace): int`

Supprime tous les documents d'un namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à supprimer |

**Retourne :** `int` - Nombre de suppressions

**Exemple :**
```php
$deleted = $repository->deleteByNamespace('App\Models\TempData');
```

---

### `deleteByClusterQuery(string $query, string $column = 'cluster'): int`

Supprime les documents correspondant à une requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |

**Retourne :** `int` - Nombre de suppressions

---

### `countByNamespace(string $namespace): int`

Compte les documents d'un namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à compter |

**Retourne :** `int` - Nombre de documents

---

### `countByClusterQuery(string $query, string $column = 'cluster'): int`

Compte les documents correspondant à une requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |

**Retourne :** `int` - Nombre de documents correspondants

---

### `existsByFingerPrint(IndexableFingerprintVO $fingerprint): bool`

Vérifie si un document existe par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint à vérifier |

**Retourne :** `bool` - `true` si le document existe

---

### `existsByNamespace(string $namespace): bool`

Vérifie si des documents existent dans un namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à vérifier |

**Retourne :** `bool` - `true` si au moins un document existe

---

### `existsByClusterQuery(string $query, string $column = 'cluster'): bool`

Vérifie si des documents correspondent à une requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |

**Retourne :** `bool` - `true` si au moins un document correspond

---

### `getDistinctNamespaces(): Collection`

Retourne tous les namespaces distincts.

**Retourne :** `Collection<int, string>` - Namespaces uniques

**Exemple :**
```php
$namespaces = $repository->getDistinctNamespaces();
// ['App\Models\User', 'App\Models\Product']
```

---

### `getDistinctClusterKeys(): Collection`

Retourne toutes les clés de cluster distinctes (multi-driver).

**Retourne :** `Collection<int, string>` - Clés de cluster uniques

**Support des drivers :**
- MySQL : `JSON_KEYS()`
- PostgreSQL : `jsonb_object_keys()`
- SQLite : Parsing JSON manuel

---

### `getDistinctClusterValues(string $key): Collection`

Retourne toutes les valeurs distinctes pour une clé de cluster donnée (multi-driver).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé de cluster |

**Retourne :** `Collection<int, string>` - Valeurs uniques

---

### `findAllWithTokens(): Collection`

Retourne tous les documents avec leurs tokens pré-chargés.

**Retourne :** `Collection<int, IndexedDocument>` - Documents avec tokens

---

### `createMany(array $records): array`

Crée plusieurs documents en une seule requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `IndexedDocumentRecord[]` | Records à créer |

**Retourne :** `array<IndexedDocument>` - Documents créés

---

## Cas d'utilisation

### Cas 1 : Indexation d'un nouveau modèle

```php
$record = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|123'),
    cluster: new ClusterVO(['status' => 'active']),
    data: StrictAssociative::from(['name' => 'John Doe'])
);

$document = $repository->create($record);
```

### Cas 2 : Recherche avec filtres cluster

```php
// Utilisateurs actifs avec rôle admin
$admins = $repository->findByClusterQuery('status=active & role=admin');

// Utilisateurs ayant au moins 5 commandes
$powerUsers = $repository->findByClusterQuery('COUNT(orders) > 5');

// Utilisateurs de Kinshasa ou Lubumbashi
$users = $repository->findByClusterQuery(
    'addresses[city=Kinshasa] | addresses[city=Lubumbashi]'
);
```

### Cas 3 : Analyse des données indexées

```php
// Distribution par type
$namespaces = $repository->getDistinctNamespaces();
foreach ($namespaces as $ns) {
    $count = $repository->countByNamespace($ns);
    echo "$ns: $count documents\n";
}

// Distribution par statut
$statuses = $repository->getDistinctClusterValues('status');
foreach ($statuses as $status) {
    $count = $repository->countByClusterQuery("status=$status");
    echo "Status '$status': $count\n";
}
```

### Cas 4 : Nettoyage par namespace

```php
// Supprimer tous les documents temporaires
$count = $repository->countByNamespace('App\Models\TempData');
if ($count > 0) {
    $repository->deleteByNamespace('App\Models\TempData');
    echo "Supprimé $count documents temporaires\n";
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Driver non supporté | `RuntimeException` | `Unsupported database driver: "{driver}"` |
| JSON invalide | - | Retourne `null` via `safeDecodeJson()` |

---

## Intégration

Ce repository est utilisé par :

- **`IndexerService`** : Pour les opérations d'indexation
- **`IndexWriter`** : Pour la création de documents
- **`IndexDeleter`** : Pour les suppressions
- **`IndexSearcher`** : Pour les recherches par IDs
- **`GenericIndexerService`** : Pour les opérations de haut niveau

---

## Performance

| Opération | Complexité | Optimisation |
|-----------|------------|--------------|
| `findByFingerPrint()` | O(1) | Index sur `fingerprint` |
| `findByNamespace()` | O(n) | `LIKE` sur `fingerprint` |
| `findByClusterQuery()` | Variable | Utilise `whereCluster()` du package `laravel-cluster` |
| `createMany()` | O(n) | Insertion en une requête |
| `getDistinctClusterKeys()` | Variable | Optimisé par driver |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| MySQL 8.0+ | ✅ Complet |
| PostgreSQL 13+ | ✅ Complet |
| SQLite 3.35+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

$repository = new IndexedDocumentRepository();

// 1. Créer un document
$record = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|123'),
    cluster: new ClusterVO(['status' => 'active', 'role' => 'admin']),
    data: StrictAssociative::from(['name' => 'John Doe', 'email' => 'john@example.com'])
);
$document = $repository->create($record);

// 2. Rechercher par fingerprint
$found = $repository->findByFingerPrint(
    new IndexableFingerprintVO('App\Models\User|123')
);

// 3. Rechercher par namespace
$users = $repository->findByNamespace('App\Models\User');

// 4. Recherche cluster avancée
$admins = $repository->findByClusterQuery('status=active & role=admin');

// 5. Compter les documents
$count = $repository->countByNamespace('App\Models\User');

// 6. Vérifier l'existence
$exists = $repository->existsByFingerPrint(
    new IndexableFingerprintVO('App\Models\User|123')
);

// 7. Obtenir les valeurs distinctes
$namespaces = $repository->getDistinctNamespaces();
$statuses = $repository->getDistinctClusterValues('status');

// 8. Supprimer
$repository->deleteByFingerPrint(
    new IndexableFingerprintVO('App\Models\User|123')
);
```

---

## Voir aussi

- `IndexedDocumentRepositoryInterface` - Interface du repository
- `IndexedDocument` - Modèle Eloquent
- `IndexedDocumentRecord` - Record de transfert
- `AbstractRepository` - Classe parente
- `IndexableFingerprintVO` - Value Object de fingerprint