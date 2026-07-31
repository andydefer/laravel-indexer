# IndexSearcher - Référence Technique

## Description

Service de recherche full-text pour les documents indexés. Fournit des capacités de recherche avec support des tokens lexicaux et métaphones, filtrage par cluster et génération de n-grams.

## Hiérarchie / Implémentations

```
IndexSearcher (classe finale)
    ├── Dépend de IndexedDocumentRepository
    ├── Dépend de IndexedTokenRepository
    ├── Dépend de TextNormalizerInterface
    ├── Dépend de IndexerConfigInterface
    └── Dépend de ClusterService
```

## Rôle principal

Ce service est le moteur de recherche du système d'indexation. Il orchestre :

- La recherche full-text avec requêtes complexes
- Le filtrage par cluster sur les documents
- La génération de n-grams pour les correspondances partielles
- La recherche phonétique via les métaphones
- L'intersection des résultats multi-grammes

### Responsabilités

1. **Recherche** : `search()`
2. **Vérification d'existence** : `exists()`
3. **Résolution des tailles de n-grams** : `resolveMinSize()`, `resolveMaxSize()`
4. **Recherche de tokens** : `searchTokens()`
5. **Intersection des résultats** : `intersectResults()`

## Détails

[Voir la classe SearchQueryRecord](https://github.com/andydefer/laravel-indexer/blob/main/src/Records/SearchQueryRecord.php)

[Voir la classe IndexableSearchResultCollection](https://github.com/andydefer/laravel-indexer/blob/main/src/Collections/IndexableSearchResultCollection.php)

## API / Méthodes publiques

### `exists(IndexableFingerprintVO $fingerprint): bool`

Vérifie l'existence d'un document par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint du document |

**Retourne :** `bool` - `true` si le document existe

**Exemple :**
```php
$fingerprint = new IndexableFingerprintVO('App\Models\User|123');
$exists = $searcher->exists($fingerprint);
```

---

### `search(SearchQueryRecord $query): IndexableSearchResultCollection`

Effectue une recherche avec la requête donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `SearchQueryRecord` | Configuration de la requête |

**Retourne :** `IndexableSearchResultCollection` - Résultats de la recherche

**Exemple :**
```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,description'),
    cluster_queries: new ClusterQueries(['cluster' => 'status=active']),
    min_size: 2,
    max_size: 4,
    limit: 10
);

$results = $searcher->search($query);
```

---

## Cas d'utilisation

### Cas 1 : Recherche simple par nom

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name')
);
$results = $searcher->search($query);
```

### Cas 2 : Recherche multi-champs

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,description,email')
);
$results = $searcher->search($query);
```

### Cas 3 : Recherche multi-grammes (AND logique)

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|doe=last_name')
);
$results = $searcher->search($query);
// Retourne les documents contenant BOTH 'john' dans name ET 'doe' dans last_name
```

### Cas 4 : Recherche avec filtrage cluster

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    cluster_queries: new ClusterQueries([
        'cluster' => 'status=active & role=admin'
    ])
);
$results = $searcher->search($query);
// Retourne les utilisateurs actifs admin nommés John
```

### Cas 5 : Recherche phonétique

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('jon=name')
);
$results = $searcher->search($query);
// Retourne également 'John' via la correspondance métaphone
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Requête vide | `InvalidArgumentException` | `Search query cannot be empty` |
| Format de requête invalide | `InvalidArgumentException` | `Invalid format. Expected "ngram=field1,field2", got ...` |

---

## Intégration

Ce service est utilisé par :

- **`IndexerService`** : Pour les opérations de recherche
- **`IndexSearcher`** : Appelé via l'interface `IndexerInterface`

### Flux de données

```
IndexerService::search()
    │
    └── IndexSearcher::search()
            │
            ├── Pour chaque n-gram
            │   ├── Recherche tokens LEXICAL
            │   ├── Recherche tokens METAPHONE
            │   └── Fusion des résultats
            │
            ├── Intersection des résultats
            ├── Application de la limite
            ├── Récupération des documents
            └── Construction des résultats
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `search()` | O(n * m) | n = nombre de n-grams, m = nombre de tokens |
| `searchTokens()` | O(n) | Index sur `(token_type, token)` |
| `intersectResults()` | O(n * m) | n = résultats, m = intersections |
| `generateNgramsFromTerm()` | O(n²) | Génération de tous les n-grams possibles |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Services\Composants\IndexSearcher;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

$searcher = new IndexSearcher(
    $documentRepository,
    $tokenRepository,
    $textNormalizer,
    $config,
    $clusterService
);

// 1. Recherche simple
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name')
);
$results = $searcher->search($query);

// 2. Recherche avec filtres
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    cluster_queries: new ClusterQueries([
        'cluster' => 'status=active'
    ]),
    limit: 20
);
$results = $searcher->search($query);

// 3. Recherche multi-grammes
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|doe=last_name')
);
$results = $searcher->search($query);

// 4. Vérification d'existence
$fingerprint = new IndexableFingerprintVO('App\Models\User|123');
$exists = $searcher->exists($fingerprint);
```

---

## Voir aussi

- `SearchQueryRecord` - Record de requête
- `SearchQueryVO` - Value Object de requête
- `IndexableSearchResultCollection` - Collection de résultats
- `GramType` - Énumération des types de tokens
- `ClusterQueries` - Filtres de cluster