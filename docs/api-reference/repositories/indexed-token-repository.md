# IndexedTokenRepository - Référence Technique

## Description

Repository concret pour les opérations de persistance sur les tokens indexés (`IndexedToken`). Implémente `IndexedTokenRepositoryInterface` et étend `AbstractRepository` pour fournir toutes les opérations CRUD, de recherche avancée et d'autocomplétion.

## Hiérarchie / Implémentations

```
AbstractRepository<IndexedToken, IndexedTokenRecord>
    └── IndexedTokenRepository
            └── Implémente IndexedTokenRepositoryInterface
```

## Rôle principal

Ce repository est le point d'accès principal pour toutes les opérations sur les tokens indexés. Il fournit :

- **Recherche par token** : Simple, par champ, type, namespace, cluster
- **Autocomplétion** : Suggestions de tokens par préfixe
- **Extraction d'IDs de documents** : Pour construire des résultats de recherche
- **Comptage** : Par type, champ, namespace, cluster
- **Suppression** : Par document, namespace, cluster, token
- **Gestion de fréquence** : Incrémentation des occurrences

## Détails

[Voir la classe IndexedTokenRepositoryInterface](https://github.com/andydefer/laravel-indexer/blob/main/src/Contracts/Repositories/IndexedTokenRepositoryInterface.php)

## API / Méthodes publiques

### `__construct()`

Initialise le repository avec le modèle et le record associés.

```php
public function __construct()
{
    parent::__construct(IndexedToken::class, IndexedTokenRecord::class);
}
```

---

### `findBy(FindByRecord $record): Collection`

Recherche des tokens avec des critères avancés incluant les filtres cluster sur le document parent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `FindByRecord` | Critères de recherche |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens correspondants

**Exemple :**
```php
$filters = new IndexedTokenFiltersRecord(
    token: 'john',
    field: 'name'
);
$findBy = new FindByRecord(
    filters: $filters,
    limit: 10,
    sortBy: new SortColumns('frequency:desc')
);
$tokens = $repository->findBy($findBy);
```

---

### `paginate(PaginateRecord $record): LengthAwarePaginator`

Paginer les résultats avec filtrage cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `PaginateRecord` | Configuration de pagination |

**Retourne :** `LengthAwarePaginator<IndexedToken>` - Résultats paginés

---

### `getModel(): Model`

Retourne l'instance du modèle Eloquent.

**Retourne :** `Model` - Instance de `IndexedToken`

---

### `findByToken(string $token): Collection`

Recherche tous les tokens par valeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByTokenAndField(string $token, string $field): Collection`

Recherche les tokens par valeur et champ.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByTokenAndType(string $token, GramType $type): Collection`

Recherche les tokens par valeur et type.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$type` | `GramType` | Type de token (`LEXICAL` ou `METAPHONE`) |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByTokenAndNamespace(string $token, string $namespace): Collection`

Recherche les tokens par valeur et namespace du document parent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$namespace` | `string` | Namespace du document |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByTokenAndClusterQuery(string $token, string $query, string $column = 'cluster', ?DatabaseDriver $driver = null): Collection`

Recherche les tokens par valeur et requête cluster sur le document parent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |
| `$driver` | `DatabaseDriver|null` | Driver de base de données |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

**Exemple :**
```php
$tokens = $repository->findByTokenAndClusterQuery(
    'john',
    'status=active & role=admin'
);
```

---

### `findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection`

Recherche les tokens par valeur, champ et namespace.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |
| `$namespace` | `string` | Namespace du document |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByTokenFieldAndClusterQuery(string $token, string $field, string $query, string $column = 'cluster', ?DatabaseDriver $driver = null): Collection`

Recherche les tokens par valeur, champ et requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |
| `$driver` | `DatabaseDriver|null` | Driver de base de données |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByTokenFieldAndDocument(string $token, string $field, string $documentId, GramType $tokenType): ?IndexedToken`

Recherche un token unique par valeur, champ, document et type.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |
| `$documentId` | `string` | ID du document |
| `$tokenType` | `GramType` | Type de token |

**Retourne :** `IndexedToken|null` - Token trouvé ou `null`

---

### `findByType(GramType $type): Collection`

Recherche tous les tokens par type.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `GramType` | Type de token (`LEXICAL` ou `METAPHONE`) |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByField(string $field): Collection`

Recherche tous les tokens par champ.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByDocumentId(string $documentId): Collection`

Recherche tous les tokens d'un document.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentId` | `string` | ID du document |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByDocumentFingerPrint(IndexableFingerprintVO $fingerprint): Collection`

Recherche tous les tokens d'un document par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint du document |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByNamespace(string $namespace): Collection`

Recherche tous les tokens des documents d'un namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace des documents |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens

---

### `findByClusterQuery(string $query, string $column = 'cluster', ?DatabaseDriver $driver = null): Collection`

Recherche les tokens dont les documents parents correspondent à une requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |
| `$driver` | `DatabaseDriver|null` | Driver de base de données |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens correspondants

---

### `autocomplete(string $prefix, ?int $limit = 10): Collection`

Suggère des tokens distincts commençant par un préfixe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe à rechercher |
| `$limit` | `int|null` | Nombre maximum de suggestions |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens suggérés

**Exemple :**
```php
$suggestions = $repository->autocomplete('jo', 5);
// ['john', 'joe', 'jonathan', ...]
```

---

### `startingWith(string $letter, ?int $limit = null): Collection`

Récupère tous les tokens commençant par une lettre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$letter` | `string` | Lettre ou préfixe |
| `$limit` | `int|null` | Nombre maximum de résultats |

**Retourne :** `Collection<IndexedToken>` - Collection des tokens correspondants

---

### `getDocumentIdsForToken(string $token): Collection`

Récupère les IDs des documents contenant un token.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |

**Retourne :** `Collection<string>` - IDs des documents

**Exemple :**
```php
$ids = $repository->getDocumentIdsForToken('john');
// ['uuid-1', 'uuid-2']
```

---

### `getDocumentIdsForTokenAndField(string $token, string $field): Collection`

Récupère les IDs des documents contenant un token dans un champ donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |

**Retourne :** `Collection<string>` - IDs des documents

---

### `getDocumentIdsForTokenAndClusterQuery(string $token, string $query, string $column = 'cluster', ?DatabaseDriver $driver = null): Collection`

Récupère les IDs des documents contenant un token et correspondant à une requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |
| `$driver` | `DatabaseDriver|null` | Driver de base de données |

**Retourne :** `Collection<string>` - IDs des documents

---

### `getDocumentIdsForTokenFieldAndClusterQuery(string $token, string $field, string $query, string $column = 'cluster', ?DatabaseDriver $driver = null): Collection`

Récupère les IDs des documents contenant un token dans un champ donné et correspondant à une requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |
| `$driver` | `DatabaseDriver|null` | Driver de base de données |

**Retourne :** `Collection<string>` - IDs des documents

---

### `countDistinctTokens(): int`

Compte le nombre de tokens distincts dans l'index.

**Retourne :** `int` - Nombre de tokens uniques

---

### `countByType(GramType $type): int`

Compte les tokens par type.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `GramType` | Type de token (`LEXICAL` ou `METAPHONE`) |

**Retourne :** `int` - Nombre de tokens

---

### `countByField(string $field): int`

Compte les tokens par champ.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ |

**Retourne :** `int` - Nombre de tokens

---

### `countByNamespace(string $namespace): int`

Compte les tokens des documents d'un namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace des documents |

**Retourne :** `int` - Nombre de tokens

---

### `countByClusterQuery(string $query, string $column = 'cluster', ?DatabaseDriver $driver = null): int`

Compte les tokens dont les documents parents correspondent à une requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |
| `$driver` | `DatabaseDriver|null` | Driver de base de données |

**Retourne :** `int` - Nombre de tokens correspondants

---

### `deleteByDocumentId(string $documentId): int`

Supprime tous les tokens d'un document.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentId` | `string` | ID du document |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByDocumentFingerPrint(IndexableFingerprintVO $fingerprint): int`

Supprime tous les tokens d'un document par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint du document |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByNamespace(string $namespace): int`

Supprime tous les tokens des documents d'un namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace des documents |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByClusterQuery(string $query, string $column = 'cluster', ?DatabaseDriver $driver = null): int`

Supprime les tokens dont les documents parents correspondent à une requête cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Expression de requête cluster |
| `$column` | `string` | Colonne des données cluster |
| `$driver` | `DatabaseDriver|null` | Driver de base de données |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByToken(string $token): int`

Supprime tous les tokens d'une valeur donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `deleteByTokenAndField(string $token, string $field): int`

Supprime les tokens par valeur et champ.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | Valeur du token |
| `$field` | `string` | Nom du champ |

**Retourne :** `int` - Nombre de tokens supprimés

---

### `getDistinctTokens(): Collection`

Retourne toutes les valeurs de tokens distinctes.

**Retourne :** `Collection<string>` - Tokens uniques

---

### `getDistinctFields(): Collection`

Retourne tous les noms de champs distincts.

**Retourne :** `Collection<string>` - Champs uniques

---

### `incrementFrequency(string $id): int`

Incrémente la fréquence d'un token de 1.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | ID du token |

**Retourne :** `int` - Nombre de lignes affectées

---

## Cas d'utilisation

### Cas 1 : Recherche full-text

```php
// Documents contenant 'john' ET 'doe'
$johnIds = $repository->getDocumentIdsForToken('john');
$doeIds = $repository->getDocumentIdsForToken('doe');
$matchingIds = $johnIds->intersect($doeIds);

// Récupérer les documents correspondants
$documents = $documentRepository->findByIds($matchingIds->toArray());
```

### Cas 2 : Autocomplétion avec contexte

```php
$suggestions = $repository->autocomplete('jo', 10);
$filtered = $suggestions->filter(function ($token) use ($namespace) {
    return $token->document->fingerprint->belongsTo($namespace);
});
```

### Cas 3 : Analyse des tokens par champ

```php
$fields = $repository->getDistinctFields();
foreach ($fields as $field) {
    $count = $repository->countByField($field);
    echo "$field: $count tokens\n";
}
```

### Cas 4 : Nettoyage après suppression de document

```php
$repository->deleteByDocumentId($documentId);
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Driver non supporté | `RuntimeException` | `Unsupported database driver: "{driver}"` |

---

## Intégration

Ce repository est utilisé par :

- **`IndexSearcher`** : Pour la recherche de tokens et autocomplétion
- **`IndexWriter`** : Pour l'insertion et mise à jour des tokens
- **`IndexDeleter`** : Pour la suppression des tokens
- **`IndexerService`** : Pour les opérations d'indexation

---

## Performance

| Opération | Complexité | Optimisation |
|-----------|------------|--------------|
| `findByToken()` | O(n) | Index sur `token` |
| `autocomplete()` | O(n) | `LIKE` sur `token` |
| `getDocumentIdsForToken()` | O(n) | Index sur `(token, document_id)` |
| `countDistinctTokens()` | O(n) | Comptage distinct |
| `incrementFrequency()` | O(1) | Mise à jour par ID |

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

use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelIndexer\Enums\GramType;

$repository = new IndexedTokenRepository();

// 1. Recherche simple
$tokens = $repository->findByToken('john');

// 2. Recherche par token et champ
$nameTokens = $repository->findByTokenAndField('john', 'name');

// 3. Recherche par type
$lexicalTokens = $repository->findByType(GramType::LEXICAL);

// 4. Autocomplétion
$suggestions = $repository->autocomplete('jo', 5);

// 5. Récupération des IDs de documents
$documentIds = $repository->getDocumentIdsForToken('john');

// 6. Comptage
$totalDistinct = $repository->countDistinctTokens();
$byField = $repository->countByField('name');

// 7. Incrémentation de fréquence
$repository->incrementFrequency($tokenId);

// 8. Suppression par document
$deleted = $repository->deleteByDocumentId($documentId);

// 9. Tokens distincts
$uniqueTokens = $repository->getDistinctTokens();
```

---

## Voir aussi

- `IndexedTokenRepositoryInterface` - Interface du repository
- `IndexedToken` - Modèle Eloquent
- `IndexedTokenRecord` - Record de transfert
- `GramType` - Énumération des types de tokens
- `AbstractRepository` - Classe parente