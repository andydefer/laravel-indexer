# IndexedDocumentRepository - Référence Technique

## Description

Repository pour la gestion des documents indexés dans Laravel Indexer. Fournit des opérations CRUD et des méthodes de recherche avancées pour les documents indexés, avec support des filtres par clusters multiples.

## Hiérarchie / Implémentations

```
AbstractRepository
    └── IndexedDocumentRepository
        └── IndexedDocumentRepositoryInterface
```

## Rôle principal

`IndexedDocumentRepository` est le point d'accès principal pour interagir avec les documents indexés en base de données. Il gère :

- **CRUD** : Création, lecture, mise à jour, suppression de documents
- **Recherche** : Par fingerprint, namespace, cluster(s), ID
- **Filtrage avancé** : Filtrage par clusters multiples avec opérateurs AND, OR, NOT
- **Métadonnées** : Récupération des namespaces et clusters distincts

## DETAILS

[Voir la classe IndexedDocumentRepository](https://github.com/andydefer/laravel-indexer/blob/main/src/Repositories/IndexedDocumentRepository.php)

## API / Méthodes publiques

### `__construct()`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `void`

**Exemple :**
```php
$repository = new IndexedDocumentRepository();
```

---

### `create(AbstractRecord $record): Model` (hérité)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `IndexedDocumentRecord` | Record du document à créer |

**Retourne :** `IndexedDocument` - Le document créé

**Exemple :**
```php
$record = IndexedDocumentRecord::from([
    'fingerprint' => 'App.Models.User|123',
    'cluster' => 'type:user|status:active',
    'data' => ['name' => 'John Doe'],
]);

$document = $repository->create($record);
```

---

### `createMany(array $records): array`

Crée plusieurs documents en une seule requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `array<IndexedDocumentRecord>` | Liste des records |

**Retourne :** `array<IndexedDocument>` - Documents créés

---

### `find(int|string $id): ?Model` (hérité)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | ID du document (UUID) |

**Retourne :** `?IndexedDocument` - Document trouvé ou `null`

---

### `findByFingerPrint(IndexableFingerPrintVO $fingerPrint): ?IndexedDocument`

Recherche un document par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Fingerprint du document |

**Retourne :** `?IndexedDocument` - Document trouvé ou `null`

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$document = $repository->findByFingerPrint($fingerPrint);
```

---

### `findByFingerprintString(string $fingerprint): ?IndexedDocument`

Recherche un document par son fingerprint (string).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `string` | Fingerprint du document |

**Retourne :** `?IndexedDocument` - Document trouvé ou `null`

---

### `findByNamespace(string $namespace): Collection`

Recherche tous les documents d'un namespace.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace (ex: `App.Models.User`) |

**Retourne :** `Collection<int, IndexedDocument>` - Collection de documents

---

### `findByCluster(ClusterVO $cluster): Collection`

Recherche les documents correspondant à un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster avec mode (AND, OR ou NOT) |

**Retourne :** `Collection<int, IndexedDocument>` - Collection de documents

**Exceptions :** `InvalidArgumentException` - Si le cluster n'a pas de mode

**Exemple :**
```php
$cluster = new ClusterVO('type:user|status:active@AND');
$documents = $repository->findByCluster($cluster);
```

---

### `findByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): Collection`

Recherche les documents correspondant à une collection de clusters.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$operator` | `string` | Opérateur: `'AND'`, `'OR'`, `'NOT'` (défaut: `'AND'`) |

**Retourne :** `Collection<int, IndexedDocument>` - Collection de documents

**Exceptions :** `InvalidArgumentException` - Si l'opérateur est invalide

**Exemple :**
```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

$documents = $repository->findByClusters($clusters, 'AND');
```

---

### `findByClusterKeyValue(string $key, string $value): Collection`

Recherche les documents contenant une paire clé-valeur spécifique dans leur cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé recherchée |
| `$value` | `string` | Valeur recherchée |

**Retourne :** `Collection<int, IndexedDocument>` - Collection de documents

---

### `findByIds(array $ids): Collection`

Recherche des documents par leurs IDs.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ids` | `array<string>` | Liste des IDs (UUID) |

**Retourne :** `Collection<int, IndexedDocument>` - Collection de documents

---

### `update(int|string $id, AbstractRecord $record): Model` (hérité)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | ID du document |
| `$record` | `IndexedDocumentRecord` | Record de mise à jour |

**Retourne :** `IndexedDocument` - Document mis à jour

**Exceptions :** `ModelNotFoundException` - Si le document n'existe pas

---

### `delete(int|string $id): bool` (hérité)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | ID du document |

**Retourne :** `bool` - `true` si supprimé

---

### `deleteByFingerPrint(IndexableFingerPrintVO $fingerPrint): int`

Supprime un document par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Fingerprint du document |

**Retourne :** `int` - Nombre de documents supprimés (0 ou 1)

---

### `deleteByFingerprintString(string $fingerprint): int`

Supprime un document par son fingerprint (string).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `string` | Fingerprint du document |

**Retourne :** `int` - Nombre de documents supprimés

---

### `deleteByNamespace(string $namespace): int`

Supprime tous les documents d'un namespace.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace |

**Retourne :** `int` - Nombre de documents supprimés

---

### `deleteByCluster(ClusterVO $cluster): int`

Supprime les documents correspondant à un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster avec mode |

**Retourne :** `int` - Nombre de documents supprimés

**Exceptions :** `InvalidArgumentException` - Si le cluster n'a pas de mode

---

### `deleteByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): int`

Supprime les documents correspondant à une collection de clusters.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$operator` | `string` | Opérateur: `'AND'`, `'OR'`, `'NOT'` |

**Retourne :** `int` - Nombre de documents supprimés

**Exceptions :** `InvalidArgumentException` - Si l'opérateur est invalide

---

### `deleteByClusterKeyValue(string $key, string $value): int`

Supprime les documents contenant une paire clé-valeur spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé |
| `$value` | `string` | Valeur |

**Retourne :** `int` - Nombre de documents supprimés

---

### `countByNamespace(string $namespace): int`

Compte les documents d'un namespace.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace |

**Retourne :** `int` - Nombre de documents

---

### `countByCluster(ClusterVO $cluster): int`

Compte les documents correspondant à un cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster avec mode |

**Retourne :** `int` - Nombre de documents

---

### `countByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): int`

Compte les documents correspondant à une collection de clusters.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$operator` | `string` | Opérateur: `'AND'`, `'OR'`, `'NOT'` |

**Retourne :** `int` - Nombre de documents

---

### `getDistinctNamespaces(): Collection`

Retourne tous les namespaces distincts présents dans l'index.

**Retourne :** `Collection<int, string>` - Liste des namespaces

---

### `getDistinctClusterKeys(): Collection`

Retourne toutes les clés de cluster distinctes.

**Retourne :** `Collection<int, string>` - Liste des clés

---

### `getDistinctClusterValues(string $key): Collection`

Retourne toutes les valeurs distinctes pour une clé de cluster donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du cluster |

**Retourne :** `Collection<int, string>` - Liste des valeurs

---

### `existsByFingerPrint(IndexableFingerPrintVO $fingerPrint): bool`

Vérifie l'existence d'un document par fingerprint.

---

### `existsByNamespace(string $namespace): bool`

Vérifie l'existence de documents dans un namespace.

---

### `existsByCluster(ClusterVO $cluster): bool`

Vérifie l'existence de documents correspondant à un cluster.

---

### `existsByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): bool`

Vérifie l'existence de documents correspondant à une collection de clusters.

---

### `findAllWithTokens(): Collection`

Retourne tous les documents avec leurs tokens chargés.

**Retourne :** `Collection<int, IndexedDocument>` - Documents avec relation `tokens`

---

## Cas d'utilisation

### Cas 1 : Recherche de tous les médecins actifs

```php
$cluster = new ClusterVO('type:user|role_doctor:true|status:active@AND');
$doctors = $repository->findByCluster($cluster);
```

### Cas 2 : Recherche par clusters multiples (AND)

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

$documents = $repository->findByClusters($clusters, 'AND');
```

### Cas 3 : Recherche par clusters multiples (OR)

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$documents = $repository->findByClusters($clusters, 'OR');
```

### Cas 4 : Exclusion par cluster (NOT)

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('status:inactive@NOT'));

$documents = $repository->findByClusters($clusters, 'NOT');
```

### Cas 5 : Nettoyage par tenant

```php
$cluster = new ClusterVO('tenant:company_abc@AND');
$deleted = $repository->deleteByCluster($cluster);
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Cluster sans mode | `InvalidArgumentException` | `Cluster must have a mode (AND, OR or NOT) to apply to query` |
| Opérateur invalide | `InvalidArgumentException` | `Invalid operator. Expected "AND", "OR" or "NOT", got "..."` |
| Document non trouvé (update) | `ModelNotFoundException` | `Model [ModelClass] not found with ID [id]` |

---

## Intégration

`IndexedDocumentRepository` est utilisé par :

- `IndexSearcher` - Recherche de documents
- `IndexWriter` - Écriture/indexation
- `IndexDeleter` - Suppression de documents
- `IndexerService` - Service principal d'indexation
- `GenericIndexerService` - Indexation générique

---

## Performance

- `findByCluster()` et `findByClusters()` utilisent `LIKE` avec `%` → indexation recommandée
- `createMany()` utilise `insert()` en lot → performant pour les gros volumes
- `findByIds()` utilise `whereIn()` → index sur `id` recommandé
- Les filtres par clusters multiples avec `NOT` peuvent être plus lents

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| Laravel 10.x+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

$repository = new IndexedDocumentRepository();

// 1. Création
$record = IndexedDocumentRecord::from([
    'fingerprint' => new IndexableFingerPrintVO('App.Models.User|1'),
    'cluster' => new ClusterVO('type:user|role_doctor:true|status:active'),
    'data' => StrictAssociative::from([
        'name' => 'Dr. John',
        'specialty' => 'Cardiology',
    ]),
]);

$doc = $repository->create($record);

// 2. Recherche par cluster (AND)
$cluster = new ClusterVO('type:user|role_doctor:true@AND');
$doctors = $repository->findByCluster($cluster);

// 3. Recherche par clusters multiples (OR)
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));
$users = $repository->findByClusters($clusters, 'OR');

// 4. Exclusion (NOT)
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('status:inactive@NOT'));
$activeUsers = $repository->findByClusters($clusters, 'NOT');

// 5. Suppression par tenant
$cluster = new ClusterVO('tenant:company_abc@AND');
$deleted = $repository->deleteByCluster($cluster);

// 6. Statistiques
$doctorCount = $repository->countByNamespace('App.Models.Doctor');
$keys = $repository->getDistinctClusterKeys();
$roles = $repository->getDistinctClusterValues('role');
```

## Voir aussi

- `ClusterVO` - Value Object pour les clusters
- `ClusterVOCollection` - Collection de clusters
- `ClusterFilterApplier` - Service d'application des filtres
- `IndexedDocument` - Modèle Eloquent
- `IndexSearcher` - Service de recherche
- `IndexWriter` - Service d'indexation
- `AbstractRepository` - Repository de base