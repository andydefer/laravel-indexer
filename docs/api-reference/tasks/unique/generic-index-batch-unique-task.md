# GenericIndexBatchUniqueTask - Référence Technique

## Description

Tâche unique qui traite un lot de modèles pour l'indexation. Reçoit une collection d'objets `IndexableVO`, récupère les instances de modèles correspondantes et les indexe. Gère la déduplication en vérifiant si un modèle est déjà indexé et le réindexe si nécessaire.

## Hiérarchie / Implémentations

```
AbstractUniqueTask
    └── GenericIndexBatchUniqueTask
```

## Rôle principal

Cette tâche est l'unité d'exécution du système d'indexation par lots. Elle :

- Reçoit un lot d'objets valeur indexables
- Récupère les instances de modèles en une seule requête par classe
- Filtre les modèles non indexables (`shouldBeIndexed()`)
- Gère la déduplication (suppression puis réindexation)
- Indexe tous les modèles valides en une seule opération

### Responsabilités

1. **Validation du payload** : Vérifie la présence et la validité des items
2. **Récupération optimisée** : Une requête par classe de modèle
3. **Filtrage** : Exclusion des modèles non indexables
4. **Déduplication** : Suppression des documents existants
5. **Indexation en masse** : `indexMany()` pour une performance optimale

## Détails

[Voir la classe AbstractUniqueTask](https://github.com/andydefer/task/blob/main/src/Abstract/AbstractUniqueTask.php)

[Voir la classe IndexableVOCollection](https://github.com/andydefer/laravel-indexer/blob/main/src/Collections/IndexableVOCollection.php)

## API / Méthodes publiques

### `process(): void`

Méthode principale de la tâche. Traite le lot d'items et les indexe.

**Retourne :** `void`

**Comportement :**
1. Valide le payload
2. Récupère les instances de modèles
3. Pour chaque item, vérifie la validité du modèle
4. Supprime les documents existants si nécessaire
5. Indexe tous les modèles valides en une seule opération

---

### `after(bool $success, ?DescriptionVO $error = null): void`

Méthode appelée après l'exécution de la tâche.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si la tâche a réussi |
| `$error` | `DescriptionVO|null` | Description de l'erreur si `$success` est `false` |

**Retourne :** `void`

---

## Architecture interne

### Flux d'exécution

```
GenericIndexBatchUniqueTask::process()
    │
    ├── Valide le payload
    │   ├── items présent
    │   └── items non vide
    │
    ├── Récupère les instances de modèles
    │   └── IndexableVOCollection::getModelInstances()
    │       └── Une requête par classe de modèle
    │
    ├── Pour chaque item
    │   │
    │   ├── Vérifie l'existence du modèle
    │   │   └── Si absent → skip avec warning
    │   │
    │   ├── Vérifie shouldBeIndexed()
    │   │   └── Si false → skip
    │   │
    │   ├── Vérifie l'existence dans l'index
    │   │   └── Si existe → supprime
    │   │
    │   └── Convertit en record
    │       └── IndexableRecordFactory::convert()
    │
    └── Indexe tous les records
        └── indexer->indexMany()
```

---

## Cas d'utilisation

### Cas 1 : Indexation d'un lot d'utilisateurs

```php
// Payload généré par l'orchestrateur
$items = new IndexableVOCollection();
$items->add(new IndexableVO(User::class, 1));
$items->add(new IndexableVO(User::class, 2));
$items->add(new IndexableVO(User::class, 3));

$payload = StrictDataObject::from(['items' => $items]);

// La tâche traite le lot
$task = new GenericIndexBatchUniqueTask();
$task->setPayload($payload);
$task->run();
```

### Cas 2 : Réindexation d'un lot avec déduplication

```php
// Si un modèle est déjà indexé, il est supprimé puis réindexé
// Items: [User|1, User|2, User|3]
// User|1 déjà indexé → supprimé puis réindexé
// User|2 non indexé → indexé
// User|3 inactif (shouldBeIndexed = false) → skip
```

### Cas 3 : Gestion des modèles inexistants

```php
// Si un modèle a été supprimé entre temps
// Items: [User|1, User|2, User|3]
// User|2 n'existe plus dans la base
// Résultat: "Item 2 not found, skipping"
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Payload invalide (sans items) | - | `Invalid payload: missing items or empty collection` |
| Items vides | - | `Invalid payload: items collection is empty` |
| Échec d'indexation | `Throwable` | Propage l'exception |

---

## Intégration

Cette tâche s'intègre avec :

- **`IndexerInterface`** : Pour l'indexation des records
- **`IndexedDocumentRepositoryInterface`** : Pour la vérification et suppression
- **`ConsoleInterface`** : Pour les alertes et logs
- **`IndexableVOCollection`** : Collection des items à traiter

### Flux de données

```
GenericIndexBatchUniqueTask
    │
    ├── Payload reçu
    │   └── items: IndexableVOCollection
    │
    ├── Récupération des instances
    │   └── getModelInstances()
    │       └── Collection<Model&Indexable>
    │
    ├── Conversion en records
    │   └── IndexableRecordFactory::convert()
    │       └── IndexedDocumentRecord
    │
    └── Indexation
        └── IndexerInterface::indexMany()
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `getModelInstances()` | O(n + m) | n = items, m = classes de modèles |
| Vérification d'existence | O(n) | Une requête par item |
| Conversion | O(n) | n = nombre d'items valides |
| `indexMany()` | O(n * tokens) | n = nombre de records |

**Optimisations :**

- `getModelInstances()` exécute une seule requête par classe de modèle
- `indexMany()` utilise le buffering pour les tokens
- Les modèles exclus par `shouldBeIndexed()` ne sont pas traités

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| Task Package 1.x+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

// 1. Création du lot
$items = new IndexableVOCollection();
$items->add(new IndexableVO(User::class, 1));
$items->add(new IndexableVO(User::class, 2));
$items->add(new IndexableVO(User::class, 3));
$items->add(new IndexableVO(Product::class, 10));
$items->add(new IndexableVO(Product::class, 11));

// 2. Création du payload
$payload = StrictDataObject::from(['items' => $items]);

// 3. Création et exécution de la tâche
$task = new GenericIndexBatchUniqueTask();
$task->setPayload($payload);
$task->run();

// Résultat attendu :
// Processing batch of 5 items...
// Item 1 already indexed, deleting and re-indexing
// Item 2 should not be indexed, skipping
// Item 3 indexing...
// Item 10 indexing...
// Item 11 indexing...
// Indexed 4 items, skipped 1 items
// Batch indexation completed successfully
```

---

## Voir aussi

- `AbstractUniqueTask` - Classe parente des tâches uniques
- `IndexableVOCollection` - Collection des objets valeur indexables
- `IndexableVO` - Value Object d'entité indexable
- `IndexableRecordFactory` - Factory de records
- `IndexerInterface` - Service d'indexation
- `IndexedDocumentRepositoryInterface` - Repository des documents