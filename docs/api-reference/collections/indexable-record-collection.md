# IndexableRecordCollection - Référence Technique

## Description

Collection typée spécialisée pour les objets `IndexedDocumentRecord`. Fournit des méthodes de filtrage, regroupement, recherche et extraction de données pour manipuler efficacement des collections d'enregistrements de documents indexés.

## Hiérarchie / Implémentations

```
AbstractTypedCollection
    └── IndexableRecordCollection
```

**Interfaces implémentées :** `TypedCollectionInterface`, `Countable`, `IteratorAggregate`, `ArrayAccess`

## Rôle principal

Cette collection sert de conteneur typé pour les enregistrements de documents indexés. Elle permet :

- Le découpage en chunks pour le traitement par lots
- Le filtrage par namespace, cluster, ou champs de données
- L'extraction des fingerprints, clusters, IDs et champs uniques
- Le regroupement par namespace ou par clé de cluster
- La recherche textuelle dans les données
- Le tri et l'extraction de valeurs par champ de données

Elle est utilisée dans les opérations d'indexation par lots où plusieurs documents doivent être traités ensemble.

## Détails

[Voir la classe IndexedDocumentRecord](https://github.com/andydefer/laravel-indexer/blob/main/src/Records/IndexedDocumentRecord.php)

## API / Méthodes publiques

### `chunk(int $size): array`

Découpe la collection en morceaux de taille spécifiée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$size` | `int` | Taille maximale de chaque morceau |

**Retourne :** `array<int, self>` - Tableau de collections

**Exemple :**
```php
$chunks = $collection->chunk(100);
foreach ($chunks as $chunk) {
    // Traiter chaque lot de 100 documents
    $this->indexer->indexMany($chunk);
}
```

---

### `filterByNamespace(string $namespace): self`

Filtre la collection pour ne conserver que les enregistrements appartenant au namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à filtrer (ex: `'App\Models\User'`) |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$users = $collection->filterByNamespace('App\Models\User');
```

---

### `filterByNamespaces(array $namespaces): self`

Filtre la collection pour ne conserver que les enregistrements appartenant à l'un des namespaces donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespaces` | `string[]` | Liste des namespaces à filtrer |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

---

### `filterByCluster(string $key, string $value): self`

Filtre la collection pour ne conserver que les enregistrements dont la valeur de cluster correspond.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du cluster à vérifier |
| `$value` | `string` | Valeur attendue |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$activeUsers = $collection->filterByCluster('status', 'active');
```

---

### `filterByClusters(array $clusters): self`

Filtre la collection pour ne conserver que les enregistrements correspondant à toutes les paires clé/valeur de cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusters` | `array<string, string>` | Tableau associatif clé → valeur |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$admins = $collection->filterByClusters([
    'status' => 'active',
    'role' => 'admin'
]);
```

---

### `filterByDataField(string $field, mixed $value): self`

Filtre la collection pour ne conserver que les enregistrements dont la valeur d'un champ de données correspond.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ de données |
| `$value` | `mixed` | Valeur attendue (comparaison stricte) |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$johns = $collection->filterByDataField('name', 'John Doe');
```

---

### `getFingerprints(): IndexableFingerPrintVOCollection`

Extrait tous les fingerprints des enregistrements de la collection.

**Retourne :** `IndexableFingerPrintVOCollection` - Collection des fingerprints

**Exemple :**
```php
$fingerprints = $collection->getFingerprints();
$this->indexer->deleteMany($fingerprints);
```

---

### `getClusters(): ClusterVOCollection`

Extrait tous les clusters des enregistrements de la collection.

**Retourne :** `ClusterVOCollection` - Collection des clusters

---

### `getIds(): StringTypedCollection`

Extrait tous les IDs des enregistrements de la collection.

**Retourne :** `StringTypedCollection` - Collection des IDs sous forme de chaînes

---

### `getUniqueDataFields(): StringTypedCollection`

Retourne la liste de tous les noms de champs de données uniques présents dans les enregistrements.

**Retourne :** `StringTypedCollection` - Collection des noms de champs uniques

**Exemple :**
```php
$fields = $collection->getUniqueDataFields();
// ['name', 'email', 'description', 'status']
```

---

### `groupByNamespace(): array`

Groupe les enregistrements par leur namespace.

**Retourne :** `array<string, self>` - Tableau associatif namespace → collection

**Exemple :**
```php
$groups = $collection->groupByNamespace();
foreach ($groups as $namespace => $records) {
    echo "$namespace: " . $records->count() . " documents";
}
```

---

### `groupByClusterKey(string $key): array`

Groupe les enregistrements par la valeur d'une clé de cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du cluster à utiliser pour le regroupement |

**Retourne :** `array<string, self>` - Tableau associatif valeur → collection

**Exemple :**
```php
$byRole = $collection->groupByClusterKey('role');
// [
//     'admin' => Collection (documents avec role=admin),
//     'user' => Collection (documents avec role=user),
//     'null' => Collection (documents sans role)
// ]
```

---

### `searchData(callable $callback): self`

Filtre les enregistrements en utilisant un callback qui opère sur leurs données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$callback` | `callable(StrictAssociative): bool` | Callback de filtrage |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$richUsers = $collection->searchData(
    fn ($data) => ($data['balance'] ?? 0) > 1000
);
```

---

### `searchTextInData(string $search): self`

Filtre les enregistrements contenant le texte donné dans l'un de leurs champs de données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$search` | `string` | Texte à rechercher (insensible à la casse) |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$documentsWithJohn = $collection->searchTextInData('John');
```

---

### `hasDataField(string $field): self`

Retourne une nouvelle collection contenant uniquement les enregistrements qui ont le champ de données spécifié.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ à vérifier |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

---

### `sortByDataField(string $field, bool $ascending = true): self`

Trie la collection par la valeur d'un champ de données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Champ de données à utiliser pour le tri |
| `$ascending` | `bool` | `true` pour croissant, `false` pour décroissant |

**Retourne :** `self` - Nouvelle collection triée

**Exemple :**
```php
$sortedByName = $collection->sortByDataField('name', true);
$sortedByPriceDesc = $collection->sortByDataField('price', false);
```

---

### `pluckDataField(string $field): StringTypedCollection`

Extrait les valeurs d'un champ de données spécifique dans tous les enregistrements.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Champ de données à extraire |

**Retourne :** `StringTypedCollection` - Collection des valeurs scalaires sous forme de chaînes

**Exemple :**
```php
$names = $collection->pluckDataField('name');
// ['John Doe', 'Jane Smith', '...']
```

---

### `containsId(string $id): bool`

Vérifie si un enregistrement avec l'ID donné existe dans la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | ID de l'entité à rechercher |

**Retourne :** `bool` - `true` si au moins un enregistrement correspond

---

### `containsNamespace(string $namespace): bool`

Vérifie si un enregistrement appartenant au namespace donné existe dans la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à rechercher |

**Retourne :** `bool` - `true` si au moins un enregistrement correspond

---

### `findById(string $id): ?IndexedDocumentRecord`

Recherche un enregistrement par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | ID de l'entité à rechercher |

**Retourne :** `IndexedDocumentRecord|null` - L'enregistrement trouvé ou `null`

---

### `findByIdAndNamespace(string $id, string $namespace): ?IndexedDocumentRecord`

Recherche un enregistrement par son ID et son namespace.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | ID de l'entité |
| `$namespace` | `string` | Namespace de l'entité |

**Retourne :** `IndexedDocumentRecord|null` - L'enregistrement trouvé ou `null`

---

## Cas d'utilisation

### Cas 1 : Indexation par lots avec découpage

Lors de l'indexation d'un grand nombre de documents, on découpe la collection pour éviter les problèmes de mémoire.

```php
$records = $this->getDocumentsToIndex(); // IndexableRecordCollection
$chunks = $records->chunk(100);

foreach ($chunks as $chunk) {
    $this->indexer->indexMany($chunk);
}
```

### Cas 2 : Filtrage et regroupement pour traitement différencié

On filtre par cluster pour traiter différemment les documents selon leur type ou leur statut.

```php
$documents = $this->documentRepository->findAll();

$activeDocs = $documents->filterByCluster('status', 'active');
$inactiveDocs = $documents->filterByCluster('status', 'inactive');

// Traitement différencié
$this->processActiveDocuments($activeDocs);
$this->archiveInactiveDocuments($inactiveDocs);
```

### Cas 3 : Recherche textuelle et validation

On utilise `searchTextInData()` pour trouver des documents contenant des mots-clés spécifiques.

```php
$documents = $this->documentRepository->findByNamespace('App\Models\Product');

// Recherche de produits contenant 'laptop' ou 'computer' dans leurs données
$laptops = $documents->searchTextInData('laptop');
$computers = $documents->searchTextInData('computer');

$allRelevant = $laptops->merge($computers)->unique();
```

### Cas 4 : Extraction de données pour rapport

On utilise `pluckDataField()` et `getUniqueDataFields()` pour générer des rapports.

```php
$documents = $this->documentRepository->findAll();

// Extraire tous les emails uniques
$emails = $documents->pluckDataField('email')->unique()->toArray();

// Connaître tous les champs disponibles
$availableFields = $documents->getUniqueDataFields()->toArray();

// Compter les documents par rôle
$byRole = $documents->groupByClusterKey('role');
foreach ($byRole as $role => $docs) {
    echo "$role: " . $docs->count() . PHP_EOL;
}
```

---

## Gestion des erreurs

Cette collection n'implémente pas de validation supplémentaire au-delà de la validation native dans `IndexedDocumentRecord`.

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `TypeError` | `... must be of type IndexedDocumentRecord, ... given` |
| Collection vide pour `first()` ou `last()` | `null` retourné | - |
| Chunk avec `$size <= 0` | Tableau vide retourné | - |

---

## Intégration

Cette collection s'intègre avec :

- **`IndexedDocumentRecord`** : Le type d'élément qu'elle contient
- **`IndexableFingerPrintVOCollection`** : Convertible via `getFingerprints()`
- **`ClusterVOCollection`** : Convertible via `getClusters()`
- **`IndexerService`** : Utilisée pour les opérations `indexMany()` et `refreshMany()`
- **`IndexWriter`** : Utilisée pour l'indexation par lots
- **`GenericIndexerService`** : Utilisée dans `indexAll()` pour les opérations par lots

### Flux de données

```
IndexableRecordCollection
    │
    ├── getFingerprints() → IndexableFingerPrintVOCollection
    ├── getClusters() → ClusterVOCollection
    ├── getIds() → StringTypedCollection
    └── chunck() → array<IndexableRecordCollection>
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `chunk()` | O(n) | Découpage linéaire sans copie profonde |
| `filterByNamespace()` | O(n) | Parcourt l'ensemble des éléments |
| `filterByClusters()` | O(n * k) | k = nombre de conditions de cluster |
| `filterByDataField()` | O(n) | Parcourt l'ensemble des éléments |
| `getFingerprints()` | O(n) | Construction d'une nouvelle collection |
| `groupByNamespace()` | O(n) | Construction du tableau de groupes |
| `searchTextInData()` | O(n * m) | n = nombre d'éléments, m = nombre de champs |
| `sortByDataField()` | O(n log n) | Tri avec `usort()` |
| `pluckDataField()` | O(n) | Parcourt l'ensemble des éléments |

**Considérations :**

- Les opérations de filtrage créent une nouvelle collection (immutabilité).
- `searchTextInData()` peut être coûteux sur de grands jeux de données car elle parcourt toutes les valeurs.
- `sortByDataField()` utilise `usort()` qui peut être lent sur de très grandes collections (> 10 000 éléments).
- Pour des collections volumineuses, privilégier les opérations en base de données plutôt que les filtrages en mémoire.

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

use AndyDefer\LaravelIndexer\Collections\IndexableRecordCollection;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

// Création et remplissage
$collection = new IndexableRecordCollection();

$record1 = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|1'),
    cluster: new ClusterVO(['status' => 'active', 'role' => 'admin']),
    data: StrictAssociative::from(['name' => 'John Doe', 'email' => 'john@example.com'])
);

$record2 = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|2'),
    cluster: new ClusterVO(['status' => 'inactive', 'role' => 'user']),
    data: StrictAssociative::from(['name' => 'Jane Smith', 'email' => 'jane@example.com'])
);

$collection->add($record1);
$collection->add($record2);

// Filtrage par cluster
$activeUsers = $collection->filterByCluster('status', 'active');
echo 'Utilisateurs actifs : ' . $activeUsers->count(); // 1

// Extraction des IDs
$ids = $collection->getIds()->toArray(); // ['1', '2']

// Regroupement par rôle
$byRole = $collection->groupByClusterKey('role');
foreach ($byRole as $role => $records) {
    echo "$role: " . $records->count() . PHP_EOL;
}
// admin: 1
// user: 1

// Recherche textuelle
$results = $collection->searchTextInData('john');
echo 'Contenant "john" : ' . $results->count(); // 1

// Découpage en chunks
$chunks = $collection->chunk(1);
echo 'Nombre de chunks : ' . count($chunks); // 2
```

---

## Voir aussi

- `IndexedDocumentRecord` - Record représentant un document indexé
- `IndexableFingerPrintVOCollection` - Collection de fingerprints
- `ClusterVOCollection` - Collection de clusters
- `IndexerService` - Service principal d'indexation
- `IndexWriter` - Service d'écriture des tokens