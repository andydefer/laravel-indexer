# IndexableFingerPrintVOCollection - Référence Technique

## Description

Collection typée spécialisée pour les objets `IndexableFingerprintVO`. Fournit des méthodes de filtrage, regroupement et recherche pour manipuler efficacement des collections d'empreintes d'entités indexables.

## Hiérarchie / Implémentations

```
AbstractTypedCollection
    └── IndexableFingerPrintVOCollection
```

**Interfaces implémentées :** `TypedCollectionInterface`, `Countable`, `IteratorAggregate`, `ArrayAccess`

## Rôle principal

Cette collection sert de conteneur typé pour les fingerprints d'entités indexables. Elle permet :

- Le filtrage par namespace (simple ou multiple)
- L'extraction des IDs et namespaces
- Le regroupement par namespace
- La recherche par valeur brute ou par combinaison ID/namespace
- La vérification d'existence par ID ou namespace

Elle est utilisée dans les opérations de batch où plusieurs entités doivent être traitées ensemble (ex: suppression multiple, indexation groupée).

## Détails

[Voir la classe IndexableFingerprintVO](https://github.com/andydefer/laravel-indexer/blob/main/src/ValueObjects/IndexableFingerprintVO.php)

## API / Méthodes publiques

### `filterByNamespace(string $namespace): self`

Filtre la collection pour ne conserver que les fingerprints appartenant au namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à filtrer (ex: `'App\Models\User'`) |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$collection = new IndexableFingerPrintVOCollection();
$collection->add(new IndexableFingerprintVO('App\Models\User|1'));
$collection->add(new IndexableFingerprintVO('App\Models\User|2'));
$collection->add(new IndexableFingerprintVO('App\Models\Product|1'));

$users = $collection->filterByNamespace('App\Models\User');
// Contient User|1 et User|2
```

---

### `filterByNamespaces(array $namespaces): self`

Filtre la collection pour ne conserver que les fingerprints appartenant à l'un des namespaces donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespaces` | `string[]` | Liste des namespaces à filtrer |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$collection = new IndexableFingerPrintVOCollection();
// ... ajout de fingerprints ...

$filtered = $collection->filterByNamespaces([
    'App\Models\User',
    'App\Models\Admin'
]);
```

---

### `getIds(): StringTypedCollection`

Extrait tous les IDs des fingerprints de la collection.

**Retourne :** `StringTypedCollection` - Collection des IDs sous forme de chaînes

**Exemple :**
```php
$ids = $collection->getIds();
// ids = ['1', '2', '3', '...']
```

---

### `getNamespaces(): StringTypedCollection`

Extrait tous les namespaces des fingerprints de la collection.

**Retourne :** `StringTypedCollection` - Collection des namespaces

**Exemple :**
```php
$namespaces = $collection->getNamespaces();
// namespaces = ['App\Models\User', 'App\Models\Product', '...']
```

---

### `groupByNamespace(): array`

Groupe les fingerprints par leur namespace.

**Retourne :** `array<string, self>` - Tableau associatif namespace → collection de fingerprints

**Exemple :**
```php
$groups = $collection->groupByNamespace();
// [
//     'App\Models\User' => IndexableFingerPrintVOCollection (User|1, User|2),
//     'App\Models\Product' => IndexableFingerPrintVOCollection (Product|1),
// ]
```

---

### `containsId(string $id): bool`

Vérifie si un fingerprint avec l'ID donné existe dans la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | ID de l'entité à rechercher |

**Retourne :** `bool` - `true` si au moins un fingerprint correspond

**Exemple :**
```php
if ($collection->containsId('42')) {
    // L'ID 42 est présent dans la collection
}
```

---

### `containsNamespace(string $namespace): bool`

Vérifie si un fingerprint appartenant au namespace donné existe dans la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à rechercher |

**Retourne :** `bool` - `true` si au moins un fingerprint correspond

**Exemple :**
```php
if ($collection->containsNamespace('App\Models\User')) {
    // La collection contient au moins un User
}
```

---

### `findByValue(string $value): ?IndexableFingerprintVO`

Recherche un fingerprint par sa valeur brute.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Valeur brute du fingerprint (ex: `'App\Models\User|123'`) |

**Retourne :** `IndexableFingerprintVO|null` - Le fingerprint trouvé ou `null`

**Exemple :**
```php
$fingerprint = $collection->findByValue('App\Models\User|123');
if ($fingerprint !== null) {
    $id = $fingerprint->getId(); // '123'
}
```

---

### `findByIdAndNamespace(string $id, string $namespace): ?IndexableFingerprintVO`

Recherche un fingerprint par son ID et son namespace.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | ID de l'entité |
| `$namespace` | `string` | Namespace de l'entité |

**Retourne :** `IndexableFingerprintVO|null` - Le fingerprint trouvé ou `null`

**Exemple :**
```php
$fingerprint = $collection->findByIdAndNamespace('123', 'App\Models\User');
// Retourne IndexableFingerprintVO('App\Models\User|123')
```

---

## Cas d'utilisation

### Cas 1 : Regrouper des entités par type pour traitement par batch

Lorsqu'on doit traiter des entités indexables de différents types, on utilise `groupByNamespace()` pour dispatcher le traitement par type.

```php
$toDelete = new IndexableFingerPrintVOCollection();
$toDelete->add(new IndexableFingerprintVO('App\Models\User|1'));
$toDelete->add(new IndexableFingerprintVO('App\Models\User|2'));
$toDelete->add(new IndexableFingerprintVO('App\Models\Product|5'));
$toDelete->add(new IndexableFingerprintVO('App\Models\Product|6'));

$groups = $toDelete->groupByNamespace();

foreach ($groups as $namespace => $fingerprints) {
    $ids = $fingerprints->getIds();
    // Supprimer tous les User, puis tous les Product, etc.
    $repository->deleteByNamespaceAndIds($namespace, $ids->toArray());
}
```

### Cas 2 : Vérification d'existence avant opération

Avant d'effectuer une opération sur des fingerprints, on peut vérifier la présence de certains éléments.

```php
$fingerprints = $this->getPendingFingerprints();

if ($fingerprints->containsId('123') && $fingerprints->containsNamespace('App\Models\User')) {
    // L'utilisateur 123 est dans la liste, on peut le traiter
    $userFingerprint = $fingerprints->findByIdAndNamespace('123', 'App\Models\User');
    $this->processFingerprint($userFingerprint);
}
```

### Cas 3 : Extraction d'IDs pour une requête en base

Pour optimiser les requêtes, on extrait les IDs sous forme de collection pour les utiliser dans un `WHERE IN`.

```php
$fingerprints = $this->getFingerprintsByCriteria($criteria);
$ids = $fingerprints->getIds()->toArray();

// Requête optimisée en une seule fois
$documents = Document::whereIn('entity_id', $ids)->get();
```

---

## Gestion des erreurs

Cette collection n'implémente pas de validation supplémentaire au-delà de la validation native dans `IndexableFingerprintVO`.

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `TypeError` | `... must be of type IndexableFingerprintVO, ... given` |
| Collection vide pour `first()` ou `last()` | `null` retourné | - |

---

## Intégration

Cette collection s'intègre avec :

- **`IndexableFingerprintVO`** : Le type d'élément qu'elle contient
- **`IndexerService`** : Utilisée pour les opérations `deleteMany()`
- **`GenericIndexerService`** : Utilisée pour les opérations par lot
- **`IndexableRecordCollection`** : Peut être convertie en fingerprints via `getFingerPrints()`

### Conversion depuis `IndexableRecordCollection`

```php
$records = new IndexableRecordCollection();
// ... ajout de records ...

$fingerprints = $records->getFingerPrints(); // IndexableFingerPrintVOCollection
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `filterByNamespace()` | O(n) | Parcourt l'ensemble des éléments |
| `filterByNamespaces()` | O(n) | Parcourt l'ensemble des éléments |
| `getIds()` | O(n) | Construction d'une nouvelle collection |
| `getNamespaces()` | O(n) | Construction d'une nouvelle collection |
| `groupByNamespace()` | O(n) | Construction du tableau de groupes |
| `containsId()` | O(n) | Recherche linéaire |
| `containsNamespace()` | O(n) | Recherche linéaire |
| `findByValue()` | O(n) | Recherche linéaire |
| `findByIdAndNamespace()` | O(n) | Recherche linéaire |

**Considérations :**

- Toutes les méthodes de recherche sont en O(n) car la collection n'est pas indexée.
- Pour des collections volumineuses (> 10 000 éléments), envisager un pré-indexage manuel.
- Les méthodes de filtrage créent une nouvelle collection (immutabilité), ce qui peut consommer de la mémoire sur de grands jeux de données.

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

use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;

// Création et remplissage
$collection = new IndexableFingerPrintVOCollection();

$collection->add(new IndexableFingerprintVO('App\Models\User|1'));
$collection->add(new IndexableFingerprintVO('App\Models\User|2'));
$collection->add(new IndexableFingerprintVO('App\Models\Product|10'));
$collection->add(new IndexableFingerprintVO('App\Models\Product|11'));
$collection->add(new IndexableFingerprintVO('App\Models\Order|100'));

// Filtrage par namespace
$users = $collection->filterByNamespace('App\Models\User');
echo 'Nombre d\'utilisateurs : ' . $users->count(); // 2

// Extraction des IDs
$allIds = $collection->getIds()->toArray();
// ['1', '2', '10', '11', '100']

// Regroupement par type
$groups = $collection->groupByNamespace();
foreach ($groups as $namespace => $fingerprints) {
    echo $namespace . ' : ' . $fingerprints->count() . ' entité(s)' . PHP_EOL;
}
// App\Models\User : 2 entité(s)
// App\Models\Product : 2 entité(s)
// App\Models\Order : 1 entité(s)

// Recherche
$fingerprint = $collection->findByIdAndNamespace('10', 'App\Models\Product');
if ($fingerprint !== null) {
    echo 'Trouvé : ' . $fingerprint->getValue(); // App\Models\Product|10
}

// Vérification d'existence
if ($collection->containsId('1')) {
    echo 'L\'ID 1 est présent dans la collection';
}
```

---

## Voir aussi

- `IndexableFingerprintVO` - Value Object représentant une empreinte d'entité indexable
- `IndexableRecordCollection` - Collection typée pour les `IndexedDocumentRecord`
- `IndexableSearchResultCollection` - Collection typée pour les résultats de recherche
- `StringTypedCollection` - Collection de chaînes de caractères typée