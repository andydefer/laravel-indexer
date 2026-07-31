# IndexableSearchResultCollection - Référence Technique

## Description

Collection typée spécialisée pour les objets `IndexableSearchResultRecord`. Fournit des méthodes de filtrage, regroupement et extraction de données pour manipuler efficacement des collections de résultats de recherche.

## Hiérarchie / Implémentations

```
AbstractTypedCollection
    └── IndexableSearchResultCollection
```

**Interfaces implémentées :** `TypedCollectionInterface`, `Countable`, `IteratorAggregate`, `ArrayAccess`

## Rôle principal

Cette collection sert de conteneur typé pour les résultats de recherche. Elle permet :

- Le filtrage par champ, type de gram, valeur de gram ou namespace
- L'extraction des IDs, fingerprints et enregistrements de documents
- Le regroupement par champ, type de gram ou namespace

Elle est utilisée comme retour des opérations de recherche via `IndexSearcher`.

## Détails

[Voir la classe IndexableSearchResultRecord](https://github.com/andydefer/laravel-indexer/blob/main/src/Records/IndexableSearchResultRecord.php)

## API / Méthodes publiques

### `filterByField(string $field): self`

Filtre la collection pour ne conserver que les résultats correspondant au champ donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ à filtrer |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$nameResults = $results->filterByField('name');
```

---

### `filterByGramType(GramType $type): self`

Filtre la collection pour ne conserver que les résultats ayant le type de gram donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `GramType` | Type de gram à filtrer (`LEXICAL` ou `METAPHONE`) |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$lexicalResults = $results->filterByGramType(GramType::LEXICAL);
$metaphoneResults = $results->filterByGramType(GramType::METAPHONE);
```

---

### `filterByGramValue(string $value): self`

Filtre la collection pour ne conserver que les résultats ayant la valeur de gram donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur du gram à filtrer |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$johnResults = $results->filterByGramValue('john');
```

---

### `filterByNamespace(string $namespace): self`

Filtre la collection pour ne conserver que les résultats appartenant au namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à filtrer (ex: `'App\Models\User'`) |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$userResults = $results->filterByNamespace('App\Models\User');
```

---

### `getIds(): StringTypedCollection`

Extrait tous les IDs des résultats de la collection.

**Retourne :** `StringTypedCollection` - Collection des IDs sous forme de chaînes

**Exemple :**
```php
$ids = $results->getIds()->toArray();
// ['1', '2', '3', ...]
```

---

### `getFingerprints(): IndexableFingerPrintVOCollection`

Extrait tous les fingerprints des résultats de la collection.

**Retourne :** `IndexableFingerPrintVOCollection` - Collection des fingerprints

**Exemple :**
```php
$fingerprints = $results->getFingerprints();
foreach ($fingerprints as $fp) {
    echo $fp->getValue(); // 'App\Models\User|1'
}
```

---

### `getItems(): IndexableRecordCollection`

Extrait tous les enregistrements de documents indexés des résultats de la collection.

**Retourne :** `IndexableRecordCollection` - Collection des enregistrements de documents

**Exemple :**
```php
$documents = $results->getItems();
// Maintenant on peut utiliser les méthodes de IndexableRecordCollection
$namespaces = $documents->getNamespaces();
```

---

### `groupByField(): array`

Groupe les résultats par nom de champ.

**Retourne :** `array<string, self>` - Tableau associatif champ → collection de résultats

**Exemple :**
```php
$byField = $results->groupByField();
// [
//     'name' => IndexableSearchResultCollection (résultats pour 'name'),
//     'email' => IndexableSearchResultCollection (résultats pour 'email'),
// ]
```

---

### `groupByGramType(): array`

Groupe les résultats par type de gram.

**Retourne :** `array<string, self>` - Tableau associatif type de gram → collection de résultats

**Exemple :**
```php
$byType = $results->groupByGramType();
// [
//     'lexical' => IndexableSearchResultCollection (résultats lexicaux),
//     'metaphone' => IndexableSearchResultCollection (résultats phonétiques),
// ]
```

---

### `groupByNamespace(): array`

Groupe les résultats par namespace.

**Retourne :** `array<string, self>` - Tableau associatif namespace → collection de résultats

**Exemple :**
```php
$byNamespace = $results->groupByNamespace();
foreach ($byNamespace as $namespace => $results) {
    echo "$namespace: " . $results->count() . " résultats";
}
```

---

## Cas d'utilisation

### Cas 1 : Analyse des résultats par type de match

On sépare les résultats lexicaux des résultats phonétiques pour analyser la qualité de la recherche.

```php
$searchResults = $this->searcher->search($query);

$lexicalMatches = $searchResults->filterByGramType(GramType::LEXICAL);
$metaphoneMatches = $searchResults->filterByGramType(GramType::METAPHONE);

echo "Matches exacts: " . $lexicalMatches->count() . PHP_EOL;
echo "Matches phonétiques: " . $metaphoneMatches->count() . PHP_EOL;

if ($lexicalMatches->isEmpty() && !$metaphoneMatches->isEmpty()) {
    // Aucun match exact, mais des suggestions phonétiques sont disponibles
    return $this->suggestAlternativeSpelling($metaphoneMatches);
}
```

### Cas 2 : Regroupement par champ pour affichage structuré

On regroupe les résultats par champ pour afficher les correspondances par catégorie.

```php
$results = $this->searcher->search($query);
$byField = $results->groupByField();

$output = [];
foreach ($byField as $field => $matches) {
    $output[$field] = $matches->getIds()->toArray();
}

// Affichage:
// name: [1, 2, 3]
// email: [4, 5]
// description: [1, 6]
```

### Cas 3 : Extraction des documents pour traitement

On extrait les documents pour les traiter individuellement.

```php
$results = $this->searcher->search($query);
$documents = $results->getItems();

// On peut maintenant utiliser les méthodes de IndexableRecordCollection
$activeDocs = $documents->filterByCluster('status', 'active');

foreach ($activeDocs as $doc) {
    // Traiter chaque document actif trouvé
    $this->processDocument($doc);
}
```

### Cas 4 : Filtrage et comptage par namespace

On analyse la répartition des résultats par namespace.

```php
$results = $this->searcher->search($query);

// Résultats pour les utilisateurs uniquement
$userResults = $results->filterByNamespace('App\Models\User');
$productResults = $results->filterByNamespace('App\Models\Product');

echo "Utilisateurs trouvés: " . $userResults->count() . PHP_EOL;
echo "Produits trouvés: " . $productResults->count() . PHP_EOL;

// IDs des utilisateurs trouvés
$userIds = $userResults->getIds()->toArray();
```

---

## Gestion des erreurs

Cette collection n'implémente pas de validation supplémentaire au-delà de la validation native dans `IndexableSearchResultRecord`.

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `TypeError` | `... must be of type IndexableSearchResultRecord, ... given` |
| Collection vide pour `first()` ou `last()` | `null` retourné | - |

---

## Intégration

Cette collection s'intègre avec :

- **`IndexableSearchResultRecord`** : Le type d'élément qu'elle contient
- **`IndexableRecordCollection`** : Convertible via `getItems()`
- **`IndexableFingerPrintVOCollection`** : Convertible via `getFingerprints()`
- **`IndexSearcher`** : Retournée par la méthode `search()`
- **`StringTypedCollection`** : Utilisée pour les IDs via `getIds()`

### Flux de données

```
IndexSearcher::search()
    │
    └── IndexableSearchResultCollection
            │
            ├── getItems() → IndexableRecordCollection
            ├── getFingerprints() → IndexableFingerPrintVOCollection
            ├── getIds() → StringTypedCollection
            ├── filterByField() → IndexableSearchResultCollection
            ├── filterByGramType() → IndexableSearchResultCollection
            ├── filterByGramValue() → IndexableSearchResultCollection
            ├── filterByNamespace() → IndexableSearchResultCollection
            ├── groupByField() → array<string, self>
            ├── groupByGramType() → array<string, self>
            └── groupByNamespace() → array<string, self>
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `filterByField()` | O(n) | Parcourt l'ensemble des éléments |
| `filterByGramType()` | O(n) | Parcourt l'ensemble des éléments |
| `filterByGramValue()` | O(n) | Parcourt l'ensemble des éléments |
| `filterByNamespace()` | O(n) | Parcourt l'ensemble des éléments |
| `getIds()` | O(n) | Construction d'une nouvelle collection |
| `getFingerprints()` | O(n) | Construction d'une nouvelle collection |
| `getItems()` | O(n) | Construction d'une nouvelle collection |
| `groupByField()` | O(n) | Construction du tableau de groupes |
| `groupByGramType()` | O(n) | Construction du tableau de groupes |
| `groupByNamespace()` | O(n) | Construction du tableau de groupes |

**Considérations :**

- Toutes les opérations sont en O(n) car la collection n'est pas indexée.
- Les méthodes de filtrage et de regroupement créent de nouvelles collections (immutabilité).
- `getItems()` crée une nouvelle `IndexableRecordCollection` qui peut être utilisée pour des opérations supplémentaires.

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet (via le package parent) |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Collections\IndexableSearchResultCollection;
use AndyDefer\LaravelIndexer\Enums\GramType;
use AndyDefer\LaravelIndexer\Records\IndexableSearchResultRecord;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

// Création et remplissage
$collection = new IndexableSearchResultCollection();

$record1 = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|1'),
    cluster: new ClusterVO(['status' => 'active']),
    data: StrictAssociative::from(['name' => 'John Doe'])
);

$record2 = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|2'),
    cluster: new ClusterVO(['status' => 'active']),
    data: StrictAssociative::from(['name' => 'Jane Smith'])
);

$collection->add(new IndexableSearchResultRecord(
    item: $record1,
    field: 'name',
    gram_value: 'john',
    gram_type: GramType::LEXICAL
));

$collection->add(new IndexableSearchResultRecord(
    item: $record2,
    field: 'name',
    gram_value: 'jane',
    gram_type: GramType::LEXICAL
));

// Filtrage par type de gram
$lexicalResults = $collection->filterByGramType(GramType::LEXICAL);
echo 'Résultats lexicaux : ' . $lexicalResults->count(); // 2

// Extraction des IDs
$ids = $collection->getIds()->toArray(); // ['1', '2']

// Regroupement par champ
$byField = $collection->groupByField();
foreach ($byField as $field => $results) {
    echo "$field: " . $results->count() . " résultats" . PHP_EOL;
}

// Extraction des documents
$documents = $collection->getItems();
echo 'Nombre de documents uniques : ' . $documents->count(); // 2

// Filtrage par namespace
$users = $collection->filterByNamespace('App\Models\User');
echo 'Résultats pour User : ' . $users->count(); // 2
```

---

## Voir aussi

- `IndexableSearchResultRecord` - Record représentant un résultat de recherche
- `IndexableRecordCollection` - Collection d'enregistrements de documents
- `IndexableFingerPrintVOCollection` - Collection de fingerprints
- `IndexSearcher` - Service de recherche
- `GramType` - Énumération des types de gram (LEXICAL, METAPHONE)