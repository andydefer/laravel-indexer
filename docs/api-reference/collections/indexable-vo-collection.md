# IndexableVOCollection - Référence Technique

## Description

Collection typée spécialisée pour gérer des objets `IndexableVO`. Elle offre des méthodes pour extraire les IDs, regrouper par classe de modèle, et récupérer les instances Eloquent en une seule requête par classe.

## Hiérarchie / Implémentations

```
AbstractTypedCollection
    └── IndexableVOCollection
```

**Hérite de :** `AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection`

**Type d'éléments :** `AndyDefer\LaravelIndexer\ValueObjects\IndexableVO`

## Rôle principal

`IndexableVOCollection` est une collection spécialisée qui :

1. **Stocke** des objets `IndexableVO` (modèle + ID)
2. **Optimise** les accès en base de données en groupant les requêtes par classe
3. **Fournit** des méthodes de filtrage et de recherche pour les opérations d'indexation

---

## API / Méthodes publiques

### `__construct()`

Crée une nouvelle instance de la collection.

**Exemple :**
```php
$collection = new IndexableVOCollection();
```

---

### `getIds(): array`

Récupère tous les IDs des éléments de la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| - | - | - |

**Retourne :** `array<int|string>` - Liste de tous les IDs

**Exemple :**
```php
$collection->add(
    new IndexableVO(User::class, 1),
    new IndexableVO(User::class, 2)
);
$ids = $collection->getIds(); // [1, 2]
```

---

### `getModelClasses(): array`

Récupère toutes les classes de modèle des éléments.

| Paramètre | Type | Description |
|-----------|------|-------------|
| - | - | - |

**Retourne :** `array<string>` - Liste des FQCN (Fully Qualified Class Names)

**Exemple :**
```php
$collection->add(
    new IndexableVO(User::class, 1),
    new IndexableVO(Doctor::class, 2)
);
$classes = $collection->getModelClasses(); // ['App\Models\User', 'App\Models\Doctor']
```

---

### `getModelInstances(): Collection`

Récupère les instances des modèles en une seule requête par classe. Les modèles non trouvés sont ignorés silencieusement. Les instances sont retournées dans l'ordre de la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| - | - | - |

**Retourne :** `Collection<int, Model&Indexable>` - Collection des instances Eloquent

**Performance :** Une seule requête SQL par classe de modèle, au lieu d'une requête par élément.

**Exemple :**
```php
$collection->add(
    new IndexableVO(User::class, 1),
    new IndexableVO(User::class, 2),
    new IndexableVO(Doctor::class, 3)
);

// ✅ Une seule requête pour User (WHERE IN (1,2))
// ✅ Une seule requête pour Doctor (WHERE IN (3))
$instances = $collection->getModelInstances();
```

---

### `groupByModelClass(): array`

Groupe les éléments par classe de modèle.

| Paramètre | Type | Description |
|-----------|------|-------------|
| - | - | - |

**Retourne :** `array<string, self>` - Tableau associatif [FQCN => sous-collection]

**Exemple :**
```php
$collection->add(
    new IndexableVO(User::class, 1),
    new IndexableVO(User::class, 2),
    new IndexableVO(Doctor::class, 3)
);

$groups = $collection->groupByModelClass();
// [
//     'App\Models\User' => IndexableVOCollection avec 2 éléments,
//     'App\Models\Doctor' => IndexableVOCollection avec 1 élément
// ]
```

---

### `filterByModelClass(string $modelClass): self`

Filtre les éléments par une classe de modèle spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle à filtrer |

**Retourne :** `self` - Nouvelle collection filtrée

**Exemple :**
```php
$users = $collection->filterByModelClass(User::class);
```

---

### `filterByModelClasses(array $modelClasses): self`

Filtre les éléments par une liste de classes de modèles.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClasses` | `array<string>` | Liste des FQCN à filtrer |

**Retourne :** `self` - Nouvelle collection filtrée

**Exemple :**
```php
$filtered = $collection->filterByModelClasses([User::class, Doctor::class]);
```

---

### `containsId(int|string $id): bool`

Vérifie si un ID spécifique existe dans la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | ID à rechercher |

**Retourne :** `bool` - `true` si l'ID existe, `false` sinon

**Exemple :**
```php
if ($collection->containsId(123)) {
    // L'ID 123 existe dans la collection
}
```

---

### `containsModelClass(string $modelClass): bool`

Vérifie si une classe de modèle spécifique existe dans la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN à rechercher |

**Retourne :** `bool` - `true` si la classe existe, `false` sinon

**Exemple :**
```php
if ($collection->containsModelClass(User::class)) {
    // La collection contient des User
}
```

---

### `findById(int|string $id): ?IndexableVO`

Récupère un élément par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int|string` | ID à rechercher |

**Retourne :** `?IndexableVO` - L'élément trouvé, ou `null` s'il n'existe pas

**Exemple :**
```php
$item = $collection->findById(123);
if ($item) {
    $modelClass = $item->getModelClass();
}
```

---

### `filterByModelClassAndIds(string $modelClass, array $ids): self`

Filtre les éléments par classe de modèle et liste d'IDs.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |
| `$ids` | `array<int|string>` | IDs à rechercher |

**Retourne :** `self` - Nouvelle collection filtrée

**Exemple :**
```php
$items = $collection->filterByModelClassAndIds(User::class, [1, 3, 5]);
```

---

## Cas d'utilisation

### Cas 1 : Indexation en masse avec une seule requête par modèle

```php
<?php

use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;

class DoctorIndexer
{
    public function indexBatch(array $doctorIds): void
    {
        $collection = new IndexableVOCollection;
        foreach ($doctorIds as $id) {
            $collection->add(new IndexableVO(Doctor::class, $id));
        }

        // ✅ Une seule requête SQL pour récupérer tous les docteurs
        $instances = $collection->getModelInstances();

        foreach ($instances as $doctor) {
            // Indexer chaque docteur...
        }
    }
}
```

### Cas 2 : Regrouper les éléments pour un traitement par lot

```php
<?php

$collection = new IndexableVOCollection;
$collection->add(
    new IndexableVO(User::class, 1),
    new IndexableVO(User::class, 2),
    new IndexableVO(Doctor::class, 3),
    new IndexableVO(Doctor::class, 4)
);

// ✅ Grouper par classe pour traiter chaque type séparément
$groups = $collection->groupByModelClass();

foreach ($groups as $modelClass => $items) {
    echo "Traitement de {$modelClass} : " . $items->count() . " éléments\n";
    // Traiter chaque groupe...
}
```

### Cas 3 : Filtrage avant l'indexation

```php
<?php

$collection = new IndexableVOCollection;
// ... ajout d'éléments ...

// ✅ Filtrer uniquement les utilisateurs
$users = $collection->filterByModelClass(User::class);

// ✅ Filtrer les médecins et les pharmacies
$medical = $collection->filterByModelClasses([Doctor::class, Pharmacy::class]);
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `InvalidArgumentException` | `Item must be instance of IndexableVO` |
| Type invalide dans les méthodes de filtrage | `TypeError` | `Argument must be of type string` |

---

## Intégration

### Avec GenericIndexBatchUniqueTask

```php
// Dans GenericIndexBatchUniqueTask
$items = IndexableVOCollection::from($payload->items);
$instances = $items->getModelInstances();

foreach ($instances as $model) {
    // Indexer le modèle
}
```

### Avec GenericOrchestratorRecurringTask

```php
// Construction de la collection
$collection = new IndexableVOCollection;
foreach ($chunk as $id) {
    $collection->add(new IndexableVO($modelClass, $id));
}

$payload = StrictDataObject::from([
    'items' => $collection,
]);
```

---

## Performance

| Opération | Complexité | Description |
|-----------|------------|-------------|
| `add()` | O(1) | Ajout d'un élément en fin de collection |
| `getIds()` | O(n) | Parcourt tous les éléments |
| `getModelClasses()` | O(n) | Parcourt tous les éléments |
| `getModelInstances()` | O(n + m) | n = nombre d'éléments, m = nombre de classes distinctes |
| `filterByModelClass()` | O(n) | Filtrage linéaire |
| `containsId()` | O(n) | Recherche linéaire |
| `findById()` | O(n) | Recherche linéaire |
| `groupByModelClass()` | O(n) | Parcourt tous les éléments |

**Optimisation clé :** `getModelInstances()` réduit les requêtes SQL de `O(n)` à `O(m)` où `m` est le nombre de classes de modèles distinctes.

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |
| PHP 8.4+ | ✅ Complet |
| PHP 8.5+ | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| Laravel 11+ | ✅ Complet |
| Laravel 12+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use App\Models\Doctor;
use App\Models\User;

// 1. Création et remplissage
$collection = new IndexableVOCollection;
$collection->add(
    new IndexableVO(User::class, 1),
    new IndexableVO(User::class, 2),
    new IndexableVO(Doctor::class, 3),
    new IndexableVO(Doctor::class, 4),
    new IndexableVO(User::class, 5)
);

// 2. Extraire les IDs
$ids = $collection->getIds(); // [1, 2, 3, 4, 5]

// 3. Extraire les classes
$classes = $collection->getModelClasses(); // ['User', 'User', 'Doctor', 'Doctor', 'User']

// 4. Filtrer par classe
$users = $collection->filterByModelClass(User::class); // 3 éléments
$doctors = $collection->filterByModelClass(Doctor::class); // 2 éléments

// 5. Grouper par classe
$groups = $collection->groupByModelClass();
// [
//     'App\Models\User' => IndexableVOCollection(3 éléments),
//     'App\Models\Doctor' => IndexableVOCollection(2 éléments)
// ]

// 6. Récupérer les instances (optimisé)
$instances = $collection->getModelInstances();
// ✅ 1 requête pour User (WHERE IN (1,2,5))
// ✅ 1 requête pour Doctor (WHERE IN (3,4))

// 7. Vérifier l'existence
$hasUser = $collection->containsModelClass(User::class); // true
$hasId = $collection->containsId(3); // true

// 8. Recherche
$item = $collection->findById(3); // IndexableVO(Doctor::class, 3)

// 9. Filtrage par classe et IDs
$filtered = $collection->filterByModelClassAndIds(User::class, [1, 5]); // 2 éléments
```

---

## Voir aussi

- `IndexableVO` - Value Object représentant un modèle indexable
- `AbstractTypedCollection` - Classe de base pour les collections typées
- `GenericIndexBatchUniqueTask` - Tâche utilisant cette collection
- `IndexableRecordCollection` - Collection pour les enregistrements indexables