# IndexSearcher - Référence Technique

## Description

Service de recherche d'index pour Laravel Indexer. Orchestre la recherche de documents indexés en combinant les correspondances lexicales et métaphoniques avec des filtres avancés par clusters.

## Hiérarchie / Implémentations

```
IndexSearcher (final)
```

## Rôle principal

`IndexSearcher` est le moteur de recherche du package. Il :

- **Recherche textuelle** : Recherche par n-grammes lexicaux et métaphoniques
- **Filtrage avancé** : Filtre les résultats par clusters avec opérateurs AND, OR, NOT
- **Intersection** : Combine les résultats de plusieurs n-grammes (AND logique)
- **Limitation** : Limite le nombre de résultats
- **Vérification** : Vérifie l'existence de documents par fingerprint

## DETAILS

[Voir la classe IndexSearcher](https://github.com/andydefer/laravel-indexer/blob/main/src/Services/Composants/IndexSearcher.php)

## API / Méthodes publiques

### `__construct()`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentRepository` | `IndexedDocumentRepository` | Repository des documents |
| `$tokenRepository` | `IndexedTokenRepository` | Repository des tokens |
| `$textNormalizer` | `TextNormalizerInterface` | Normaliseur de texte |
| `$config` | `IndexerConfigInterface` | Configuration de l'indexeur |

**Retourne :** `void`

**Exemple :**
```php
$searcher = new IndexSearcher(
    $documentRepository,
    $tokenRepository,
    $textNormalizer,
    $config
);
```

---

### `exists(IndexableFingerPrintVO $fingerprint): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerPrintVO` | Fingerprint du document |

**Retourne :** `bool` - `true` si le document existe

**Exemple :**
```php
$fingerprint = new IndexableFingerPrintVO('App.Models.User|123');
$exists = $searcher->exists($fingerprint);
```

---

### `search(SearchQueryRecord $query): IndexableSearchResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `SearchQueryRecord` | Requête de recherche |

**Retourne :** `IndexableSearchResultCollection` - Collection de résultats

**Exemple :**
```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('type:user|status:active@AND'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    clusters: $clusters,
    clustersOperator: 'AND',
    limit: 20
);

$results = $searcher->search($query);
```

---

## Méthodes internes (privées)

### `resolveMinSize(SearchQueryRecord $query): int`

Résout la taille minimale des n-grammes.

- Utilise la valeur configurée ou celle de la requête
- Vérifie les limites configurées

---

### `resolveMaxSize(SearchQueryRecord $query): int`

Résout la taille maximale des n-grammes.

- Utilise la valeur configurée ou celle de la requête
- Vérifie les limites configurées

---

### `searchTokens()`

Recherche les tokens correspondant aux critères.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngram` | `string` | N-gramme à rechercher |
| `$fields` | `array<string>` | Champs à filtrer |
| `$clusters` | `ClusterVOCollection` | Collection de clusters |
| `$clustersOperator` | `?string` | Opérateur entre clusters |
| `$type` | `GramType` | Type de token (LEXICAL/METAPHONE) |
| `$minSize` | `int` | Taille minimale des n-grammes |
| `$maxSize` | `int` | Taille maximale des n-grammes |

**Retourne :** `Collection<int, string>` - IDs des documents

---

### `intersectResults(array $results): Collection`

Calcule l'intersection de plusieurs ensembles de résultats.

**Retourne :** `Collection<int, string>` - IDs des documents

---

### `findMatchInfo()`

Trouve les informations de correspondance pour un document.

**Retourne :** `?array{field: string, gram_value: string, gram_type: GramType}`

---

### `generateNgramsFromTerm(string $term, int $minSize, int $maxSize): array`

Génère tous les n-grammes possibles d'un terme.

**Retourne :** `array<string>` - N-grammes uniques

---

## Cas d'utilisation

### Cas 1 : Recherche simple sans clusters

```php
$clusters = new ClusterVOCollection();

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    clusters: $clusters,
    clustersOperator: 'AND',
    limit: 20
);

$results = $searcher->search($query);
```

### Cas 2 : Recherche avec cluster AND

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('type:user@AND'));
$clusters->add(new ClusterVO('status:active@AND'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    clusters: $clusters,
    clustersOperator: 'AND',
    limit: 20
);

$results = $searcher->search($query);
```

### Cas 3 : Recherche avec cluster OR

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    clusters: $clusters,
    clustersOperator: 'OR',
    limit: 20
);

$results = $searcher->search($query);
```

### Cas 4 : Recherche avec cluster NOT

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('status:inactive@NOT'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    clusters: $clusters,
    clustersOperator: 'NOT',
    limit: 20
);

$results = $searcher->search($query);
```

### Cas 5 : Recherche multi-termes (AND)

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('type:user@AND'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|developer=description'),
    clusters: $clusters,
    clustersOperator: 'AND',
    limit: 20,
    min_size: 3,
    max_size: 5
);

$results = $searcher->search($query);
```

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Cluster sans mode | Lève `InvalidArgumentException` via `ClusterFilterApplier` |
| Aucun n-gramme généré | Retourne une collection vide |
| Aucun token trouvé | Passe au n-gramme suivant |
| Aucun document trouvé | Retourne une collection vide |
| Opérateur de clusters invalide | Lève `InvalidArgumentException` |

---

## Flux d'exécution

```
1. Normalisation du n-gramme
2. Génération des n-grammes (lexicaux)
3. Recherche des tokens LEXICAL
4. Génération du métaphone
5. Recherche des tokens METAPHONE
6. Union LEXICAL ∪ METAPHONE
7. Application des filtres de clusters (AND/OR/NOT)
8. Intersection avec les résultats précédents (AND)
9. Application de la limite
10. Récupération des documents
11. Création des résultats
```

## Performance

- **Complexité** : O(n × m) où n = nombre de n-grammes, m = nombre de tokens
- **Optimisations** :
  - Utilisation de `distinct()` et `unique()`
  - Intersection progressive (AND)
  - Utilisation des IDs plutôt que des objets complets
  - Limitation en base de données
  - Filtrage par clusters optimisé via `ClusterFilterApplier`

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

use AndyDefer\LaravelIndexer\Services\Composants\IndexSearcher;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerPrintVO;

$searcher = app(IndexSearcher::class);

// 1. Vérification d'existence
$fingerprint = new IndexableFingerPrintVO('App.Models.User|123');
if ($searcher->exists($fingerprint)) {
    echo "Document exists";
}

// 2. Recherche simple
$clusters = new ClusterVOCollection();

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    clusters: $clusters,
    clustersOperator: 'AND',
    limit: 20
);
$results = $searcher->search($query);

// 3. Recherche avec clusters AND
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('type:user@AND'));
$clusters->add(new ClusterVO('status:active@AND'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    clusters: $clusters,
    clustersOperator: 'AND',
    limit: 20
);
$results = $searcher->search($query);

// 4. Recherche avec clusters OR
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    clusters: $clusters,
    clustersOperator: 'OR',
    limit: 20
);
$results = $searcher->search($query);

// 5. Parcours des résultats
foreach ($results as $result) {
    echo $result->item->fingerprint->getValue() . "\n";
    echo "Field: " . $result->field . "\n";
    echo "Gram value: " . $result->gram_value . "\n";
    echo "Gram type: " . $result->gram_type->value . "\n";
}
```

## Voir aussi

- `SearchQueryRecord` - Record de requête
- `ClusterVO` - Value Object pour les clusters
- `ClusterVOCollection` - Collection de clusters
- `ClusterFilterApplier` - Service d'application des filtres de clusters
- `IndexedDocumentRepository` - Repository des documents
- `IndexedTokenRepository` - Repository des tokens
- `GramType` - Enum des types de tokens
- [Laravel Indexer - Documentation](https://github.com/andydefer/laravel-indexer)