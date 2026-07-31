# IndexableVOCollection - Référence Technique

## Description

Collection typée spécialisée pour les objets `IndexableVO`. Fournit des méthodes de filtrage, regroupement et récupération de modèles pour manipuler efficacement des collections d'objets valeur indexables.

## Hiérarchie / Implémentations

```
AbstractTypedCollection
    └── IndexableVOCollection
```

**Interfaces implémentées :** `TypedCollectionInterface`, `Countable`, `IteratorAggregate`, `ArrayAccess`

## Rôle principal

Cette collection sert de conteneur typé pour les objets `IndexableVO` qui représentent des entités à indexer. Elle permet :

- L'extraction des IDs et classes de modèles
- La récupération optimisée des instances de modèles (une requête par classe)
- Le filtrage par classe de modèle
- Le regroupement par classe de modèle
- La recherche par ID ou combinaison classe/ID

Elle est utilisée dans les opérations de batch où des entités de différents types doivent être traitées ensemble, notamment dans les tâches d'indexation orchestrées.

## Détails

[Voir la classe IndexableVO](https://github.com/andydefer/laravel-indexer/blob/main/src/ValueObjects/IndexableVO.php)

## API / Méthodes publiques

### `getIds(): array`

Extrait tous les IDs des objets valeur de la collection.

**Retourne :** `array<int|string>` - Tableau des IDs

**Exemple :**
```php
$ids = $collection->getIds();
// ['1', '2', '3', ...]
```

---

### `getModelClasses(): array`

Extrait tous les noms de classes de modèles des objets valeur de la collection.

**Retourne :** `array<string>` - Tableau des noms de classes qualifiés

**Exemple :**
```php
$classes = $collection->getModelClasses();
// ['App\Models\User', 'App\Models\Product', ...]
```

---

### `getModelInstances(): Collection`

Récupère les instances réelles des modèles pour tous les objets valeur en une seule requête par classe.

Cette méthode groupe les IDs par classe de modèle et exécute une requête par classe, ce qui est plus efficace que de récupérer chaque modèle individuellement. Les modèles manquants sont ignorés silencieusement. Les résultats sont retournés dans l'ordre de la collection originale.

**Retourne :** `Collection<int, Model&Indexable>` - Collection d'instances de modèles

**Exemple :**
```php
$instances = $collection->getModelInstances();
foreach ($instances as $model) {
    echo $model->getKey(); // ID du modèle
}
```

**Optimisation :** Si la collection contient 100 utilisateurs et 50 produits, cette méthode exécutera 2 requêtes SQL au lieu de 150.

---

### `groupByModelClass(): array`

Groupe les objets valeur par leur classe de modèle.

**Retourne :** `array<string, self>` - Tableau associatif classe de modèle → collection d'objets valeur

**Exemple :**
```php
$groups = $collection->groupByModelClass();
foreach ($groups as $class => $items) {
    echo "$class: " . $items->count() . " éléments" . PHP_EOL;
}
// App\Models\User: 10 éléments
// App\Models\Product: 5 éléments
```

---

### `filterByModelClass(string $modelClass): self`

Filtre la collection pour ne conserver que les objets valeur appartenant à la classe de modèle donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié complet |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$users = $collection->filterByModelClass('App\Models\User');
```

---

### `filterByModelClasses(array $modelClasses): self`

Filtre la collection pour ne conserver que les objets valeur appartenant à l'une des classes de modèles données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClasses` | `string[]` | Liste des noms de classes qualifiés |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$usersAndAdmins = $collection->filterByModelClasses([
    'App\Models\User',
    'App\Models\Admin'
]);
```

---

### `containsId(int|string $id): bool`

Vérifie si un objet valeur avec l'ID donné existe dans la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | ID de l'entité à rechercher |

**Retourne :** `bool` - `true` si au moins un objet valeur correspond

---

### `containsModelClass(string $modelClass): bool`

Vérifie si un objet valeur appartenant à la classe de modèle donnée existe dans la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié à rechercher |

**Retourne :** `bool` - `true` si au moins un objet valeur correspond

---

### `findById(int|string $id): ?IndexableVO`

Recherche un objet valeur par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | ID de l'entité à rechercher |

**Retourne :** `IndexableVO|null` - L'objet valeur trouvé ou `null`

---

### `filterByModelClassAndIds(string $modelClass, array $ids): self`

Filtre la collection pour ne conserver que les objets valeur correspondant à la classe de modèle et aux IDs donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié |
| `$ids` | `array<int|string>` | IDs des entités à filtrer |

**Retourne :** `self` - Nouvelle collection contenant les éléments correspondants

**Exemple :**
```php
$specificUsers = $collection->filterByModelClassAndIds(
    'App\Models\User',
    ['1', '2', '3']
);
```

---

## Cas d'utilisation

### Cas 1 : Récupération optimisée de modèles pour batch

Lorsqu'on doit traiter des entités de différents types, on utilise `getModelInstances()` pour minimiser le nombre de requêtes SQL.

```php
$items = new IndexableVOCollection();
$items->add(new IndexableVO('App\Models\User', 1));
$items->add(new IndexableVO('App\Models\User', 2));
$items->add(new IndexableVO('App\Models\Product', 5));
$items->add(new IndexableVO('App\Models\Product', 6));

// Une seule requête par classe = 2 requêtes SQL
$instances = $items->getModelInstances();

foreach ($instances as $model) {
    if ($model->shouldBeIndexed()) {
        $this->indexer->index($model);
    }
}
```

### Cas 2 : Traitement différencié par type

On utilise `groupByModelClass()` pour traiter différemment les entités selon leur type.

```php
$items = $this->getPendingItems();
$groups = $items->groupByModelClass();

foreach ($groups as $modelClass => $itemsOfType) {
    $ids = $itemsOfType->getIds();
    $this->logger->info("Traitement de $modelClass: " . count($ids) . " éléments");
    
    // Traitement spécifique par type
    $this->processBatch($modelClass, $ids);
}
```

### Cas 3 : Validation et filtrage avant traitement

On vérifie la présence d'éléments spécifiques avant d'exécuter une opération.

```php
$items = $this->getPendingItems();

// Vérifier qu'il y a des utilisateurs à traiter
if ($items->containsModelClass('App\Models\User')) {
    $userItems = $items->filterByModelClass('App\Models\User');
    $this->processUsers($userItems);
}

// Vérifier qu'un utilisateur spécifique est présent
if ($items->containsId('123')) {
    $user = $items->findById('123');
    $this->prioritizeUser($user);
}
```

### Cas 4 : Filtrage par classe et IDs pour sous-ensemble

On crée un sous-ensemble pour un traitement ciblé.

```php
$items = $this->getPendingItems();

// Traiter uniquement les produits avec IDs 10, 11, 12
$products = $items->filterByModelClassAndIds(
    'App\Models\Product',
    ['10', '11', '12']
);

foreach ($products as $productVO) {
    $model = $productVO->getInstance();
    $this->indexer->index($model);
}
```

---

## Gestion des erreurs

Cette collection n'implémente pas de validation supplémentaire au-delà de la validation native dans `IndexableVO`.

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `TypeError` | `... must be of type IndexableVO, ... given` |
| Collection vide pour `first()` ou `last()` | `null` retourné | - |
| Modèle non trouvé dans `getInstance()` | `ModelNotFoundException` | `Model X with ID Y not found` |

---

## Intégration

Cette collection s'intègre avec :

- **`IndexableVO`** : Le type d'élément qu'elle contient
- **`GenericOrchestratorRecurringTask`** : Utilisée pour les opérations de batch
- **`GenericIndexBatchUniqueTask`** : Utilisée pour le traitement des lots
- **`IndexerService`** : Les modèles récupérés sont envoyés à l'indexeur

### Flux de données

```
GenericOrchestratorRecurringTask
    │
    └── Crée IndexableVOCollection
            │
            └── GenericIndexBatchUniqueTask
                    │
                    ├── getModelInstances() → Collection<Model&Indexable>
                    └── Indexe les modèles via IndexerService
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `getIds()` | O(n) | Parcourt l'ensemble des éléments |
| `getModelClasses()` | O(n) | Parcourt l'ensemble des éléments |
| `getModelInstances()` | O(n + m) | n = nombre d'éléments, m = nombre de classes de modèles |
| `groupByModelClass()` | O(n) | Construction du tableau de groupes |
| `filterByModelClass()` | O(n) | Parcourt l'ensemble des éléments |
| `filterByModelClasses()` | O(n * k) | k = nombre de classes |
| `containsId()` | O(n) | Recherche linéaire |
| `containsModelClass()` | O(n) | Recherche linéaire |
| `findById()` | O(n) | Recherche linéaire |
| `filterByModelClassAndIds()` | O(n) | Parcourt l'ensemble des éléments |

**Optimisation de `getModelInstances()` :**

La méthode `getModelInstances()` est optimisée pour réduire le nombre de requêtes SQL. Au lieu d'exécuter une requête par élément, elle :

1. Groupe les IDs par classe de modèle
2. Exécute une seule requête `WHERE IN` par classe
3. Maintient l'ordre original de la collection

**Exemple :**

```
Collection: 100 User, 50 Product, 30 Order
Sans optimisation: 180 requêtes SQL
Avec optimisation: 3 requêtes SQL
```

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

use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;

// Création et remplissage
$collection = new IndexableVOCollection();

$collection->add(new IndexableVO('App\Models\User', 1));
$collection->add(new IndexableVO('App\Models\User', 2));
$collection->add(new IndexableVO('App\Models\User', 3));
$collection->add(new IndexableVO('App\Models\Product', 10));
$collection->add(new IndexableVO('App\Models\Product', 11));
$collection->add(new IndexableVO('App\Models\Order', 100));

// Extraction des IDs
$allIds = $collection->getIds();
// [1, 2, 3, 10, 11, 100]

// Extraction des classes
$classes = $collection->getModelClasses();
// ['App\Models\User', 'App\Models\User', 'App\Models\User', 'App\Models\Product', ...]

// Classes uniques
$uniqueClasses = array_unique($classes);
// ['App\Models\User', 'App\Models\Product', 'App\Models\Order']

// Regroupement par classe
$groups = $collection->groupByModelClass();
foreach ($groups as $class => $items) {
    echo "$class: " . $items->count() . PHP_EOL;
}
// App\Models\User: 3
// App\Models\Product: 2
// App\Models\Order: 1

// Filtrage par classe
$users = $collection->filterByModelClass('App\Models\User');
echo 'Utilisateurs: ' . $users->count(); // 3

// Filtrage par classes multiples
$usersAndOrders = $collection->filterByModelClasses([
    'App\Models\User',
    'App\Models\Order'
]);
echo 'Utilisateurs + Commandes: ' . $usersAndOrders->count(); // 4

// Récupération optimisée des instances
$instances = $collection->getModelInstances();
foreach ($instances as $model) {
    echo get_class($model) . ':' . $model->getKey() . PHP_EOL;
}

// Vérification d'existence
if ($collection->containsId(2)) {
    $user = $collection->findById(2);
    echo 'Trouvé: ' . $user->getModelClass() . ':' . $user->getId();
}

// Filtrage par classe et IDs
$specificProducts = $collection->filterByModelClassAndIds(
    'App\Models\Product',
    [10, 11]
);
echo 'Produits 10 et 11: ' . $specificProducts->count(); // 2
```

---

## Voir aussi

- `IndexableVO` - Value Object représentant une entité indexable
- `GenericOrchestratorRecurringTask` - Tâche récurrente qui utilise cette collection
- `GenericIndexBatchUniqueTask` - Tâche unique qui traite les lots
- `IndexerService` - Service principal d'indexation