# IndexedTokenRepository - Référence Technique

## Description

Repository pour la gestion des tokens indexés dans Laravel Indexer. Fournit des opérations CRUD, des méthodes de recherche avancées et des filtres par clusters multiples pour les tokens.

## Hiérarchie / Implémentations

```
AbstractRepository
    └── IndexedTokenRepository
        └── IndexedTokenRepositoryInterface
```

## Rôle principal

`IndexedTokenRepository` est le point d'accès principal pour interagir avec les tokens indexés en base de données. Il gère :

- **CRUD** : Création, lecture, mise à jour, suppression de tokens
- **Recherche** : Par token, type, champ, document, namespace, cluster(s)
- **Filtrage avancé** : Filtrage par clusters multiples avec opérateurs AND, OR, NOT
- **Autocomplétion** : Suggestions de tokens par préfixe
- **Agrégation** : Récupération d'IDs de documents par token

## DETAILS

[Voir la classe IndexedTokenRepository](https://github.com/andydefer/laravel-indexer/blob/main/src/Repositories/IndexedTokenRepository.php)

## API / Méthodes publiques

### `__construct()`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `void`

**Exemple :**
```php
$repository = new IndexedTokenRepository();
```

---

### `create(AbstractRecord $record): Model` (hérité)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `IndexedTokenRecord` | Record du token |

**Retourne :** `IndexedToken` - Token créé

**Exemple :**
```php
$record = new IndexedTokenRecord(
    document_id: $docId,
    token_type: GramType::LEXICAL,
    token: 'john',
    field: 'name',
    original_text: 'John'
);

$token = $repository->create($record);
```

---

### `findByToken(string $token): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `findByType(GramType $type): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `GramType` | Type de token (`LEXICAL` ou `METAPHONE`) |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `findByField(string $field): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ (ex: `name`, `email`) |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `findByDocumentId(string $documentId): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentId` | `string` | ID du document (UUID) |

**Retourne :** `Collection<int, IndexedToken>` - Tokens du document

---

### `findByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Fingerprint du document |

**Retourne :** `Collection<int, IndexedToken>` - Tokens du document

---

### `findByNamespace(string $namespace): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace (ex: `App.Models.User`) |

**Retourne :** `Collection<int, IndexedToken>` - Tokens du namespace

---

### `findByCluster(ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster avec mode |

**Retourne :** `Collection<int, IndexedToken>` - Tokens correspondants

**Exceptions :** `InvalidArgumentException` - Si le cluster n'a pas de mode

**Exemple :**
```php
$cluster = new ClusterVO('type:user|status:active@AND');
$tokens = $repository->findByCluster($cluster);
```

---

### `findByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$operator` | `string` | Opérateur: `'AND'`, `'OR'`, `'NOT'` (défaut: `'AND'`) |

**Retourne :** `Collection<int, IndexedToken>` - Tokens correspondants

**Exceptions :** `InvalidArgumentException` - Si l'opérateur est invalide

**Exemple :**
```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$tokens = $repository->findByClusters($clusters, 'OR');
```

---

### `findByClusterKeyValue(string $key, string $value): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du cluster |
| `$value` | `string` | Valeur du cluster |

**Retourne :** `Collection<int, IndexedToken>` - Tokens correspondants

---

### `findByTokenAndField(string $token, string $field): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `findByTokenAndType(string $token, GramType $type): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$type` | `GramType` | Type de token |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `findByTokenAndNamespace(string $token, string $namespace): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$namespace` | `string` | Namespace |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `findByTokenAndCluster(string $token, ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$cluster` | `ClusterVO` | Cluster avec mode |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `findByTokenAndClusters(string $token, ClusterVOCollection $clusters, string $operator = 'AND'): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$operator` | `string` | Opérateur: `'AND'`, `'OR'`, `'NOT'` |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |
| `$namespace` | `string` | Namespace |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `autocomplete(string $prefix, ?int $limit = 10): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe de recherche |
| `$limit` | `int|null` | Limite de résultats |

**Retourne :** `Collection<int, IndexedToken>` - Tokens distincts

**Exemple :**
```php
$tokens = $repository->autocomplete('joh', 5);
// ['john', 'johanna', 'johnson']
```

---

### `startingWith(string $letter, ?int $limit = null): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$letter` | `string` | Lettre de début |
| `$limit` | `int|null` | Limite de résultats |

**Retourne :** `Collection<int, IndexedToken>` - Tokens trouvés

---

### `getDocumentIdsForToken(string $token): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |

**Retourne :** `Collection<int, string>` - IDs des documents

---

### `getDocumentIdsForTokenAndField(string $token, string $field): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |

**Retourne :** `Collection<int, string>` - IDs des documents

---

### `getDocumentIdsForTokenAndCluster(string $token, ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$cluster` | `ClusterVO` | Cluster avec mode |

**Retourne :** `Collection<int, string>` - IDs des documents

---

### `getDocumentIdsForTokenAndClusters(string $token, ClusterVOCollection $clusters, string $operator = 'AND'): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$operator` | `string` | Opérateur: `'AND'`, `'OR'`, `'NOT'` |

**Retourne :** `Collection<int, string>` - IDs des documents

---

### `getDocumentIdsForTokenFieldAndCluster(string $token, string $field, ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |
| `$cluster` | `ClusterVO` | Cluster avec mode |

**Retourne :** `Collection<int, string>` - IDs des documents

---

### `getDocumentIdsForTokenFieldAndClusters(string $token, string $field, ClusterVOCollection $clusters, string $operator = 'AND'): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$operator` | `string` | Opérateur: `'AND'`, `'OR'`, `'NOT'` |

**Retourne :** `Collection<int, string>` - IDs des documents

---

### `findByTokenFieldAndDocument(string $token, string $field, string $documentId, GramType $tokenType): ?IndexedToken`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |
| `$documentId` | `string` | ID du document |
| `$tokenType` | `GramType` | Type de token |

**Retourne :** `?IndexedToken` - Token trouvé ou `null`

---

### `countDistinctTokens(): int`

**Retourne :** `int` - Nombre de tokens distincts

---

### `countByType(GramType $type): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `GramType` | Type de token |

**Retourne :** `int` - Nombre de tokens

---

### `countByField(string $field): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ |

**Retourne :** `int` - Nombre de tokens

---

### `countByNamespace(string $namespace): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace |

**Retourne :** `int` - Nombre de tokens

---

### `deleteByDocumentId(string $documentId): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentId` | `string` | ID du document |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Fingerprint du document |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByNamespace(string $namespace): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByCluster(ClusterVO $cluster): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster avec mode |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByClusters(ClusterVOCollection $clusters, string $operator = 'AND'): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$operator` | `string` | Opérateur: `'AND'`, `'OR'`, `'NOT'` |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByClusterKeyValue(string $key, string $value): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du cluster |
| `$value` | `string` | Valeur du cluster |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByToken(string $token): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByTokenAndField(string $token, string $field): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `getDistinctTokens(): Collection`

**Retourne :** `Collection<int, string>` - Tokens distincts

---

### `getDistinctFields(): Collection`

**Retourne :** `Collection<int, string>` - Champs distincts

---

### `incrementFrequency(string $id): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | ID du token |

**Retourne :** `int` - Nouveau compteur de fréquence

---

## Cas d'utilisation

### Cas 1 : Recherche de tokens par clusters multiples (OR)

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$tokens = $repository->findByClusters($clusters, 'OR');
```

### Cas 2 : Recherche de tokens par token et clusters

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('type:user@AND'));

$tokens = $repository->findByTokenAndClusters('john', $clusters, 'AND');
```

### Cas 3 : Récupération des IDs de documents par token et clusters

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$documentIds = $repository->getDocumentIdsForTokenAndClusters('john', $clusters, 'OR');
```

### Cas 4 : Suppression par clusters

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('status:inactive@AND'));

$deleted = $repository->deleteByClusters($clusters, 'AND');
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Cluster sans mode | `InvalidArgumentException` | `Cluster must have a mode (AND, OR or NOT) to apply to query` |
| Opérateur invalide | `InvalidArgumentException` | `Invalid operator. Expected "AND", "OR" or "NOT", got "..."` |

---

## Intégration

`IndexedTokenRepository` est utilisé par :

- `IndexSearcher` - Recherche de tokens
- `IndexWriter` - Écriture/indexation des tokens
- `IndexDeleter` - Suppression de tokens
- `IndexerService` - Service principal d'indexation
- `HermesRepository` - Recherche avancée (package Laravel Hermes)

---

## Performance

- `autocomplete()` utilise `LIKE` avec `prefix%` → index sur `token` recommandé
- `whereHas('document')` peut être lent sur de grands volumes
- `distinct()` et `pluck()` sont optimisés pour les agrégations
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

use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Records\IndexedTokenRecord;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;

$repository = new IndexedTokenRepository();

// 1. Création d'un token
$record = new IndexedTokenRecord(
    document_id: 'uuid-123',
    token_type: GramType::LEXICAL,
    token: 'john',
    field: 'name',
    original_text: 'John'
);

$token = $repository->create($record);

// 2. Recherche par autocomplétion
$suggestions = $repository->autocomplete('joh', 10);

// 3. Recherche par clusters (OR)
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$tokens = $repository->findByClusters($clusters, 'OR');

// 4. Recherche par token et clusters (AND)
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('type:user@AND'));
$clusters->add(new ClusterVO('status:active@AND'));

$tokens = $repository->findByTokenAndClusters('john', $clusters, 'AND');

// 5. Récupération des IDs de documents
$documentIds = $repository->getDocumentIdsForTokenAndClusters('john', $clusters, 'AND');

// 6. Incrémentation de la fréquence
$repository->incrementFrequency($token->id);

// 7. Suppression par clusters
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('status:inactive@AND'));
$deleted = $repository->deleteByClusters($clusters, 'AND');

// 8. Statistiques
$distinctTokens = $repository->getDistinctTokens();
$distinctFields = $repository->getDistinctFields();
$count = $repository->countDistinctTokens();
```

## Voir aussi

- `ClusterVO` - Value Object pour les clusters
- `ClusterVOCollection` - Collection de clusters
- `ClusterFilterApplier` - Service d'application des filtres
- `GramType` - Enum des types de tokens
- `IndexedToken` - Modèle Eloquent
- `IndexSearcher` - Service de recherche
- `IndexWriter` - Service d'indexation
- `AbstractRepository` - Repository de base