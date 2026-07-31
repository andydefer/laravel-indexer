# GenericIndexerService - Référence Technique

## Description

Service d'indexation générique fournissant une API de haut niveau pour l'indexation, le rafraîchissement, la suppression et l'interrogation des modèles Eloquent implémentant le contrat `Indexable`.

## Hiérarchie / Implémentations

```
GenericIndexerService (classe finale)
    ├── Implémente GenericIndexerInterface
    ├── Dépend de IndexerInterface
    ├── Dépend de IndexedDocumentRepositoryInterface
    └── Dépend de IndexerConfigInterface
```

## Rôle principal

Ce service est le point d'entrée principal pour toutes les opérations d'indexation sur les modèles Eloquent. Il orchestre :

- L'indexation unitaire et par lots
- La réindexation complète
- La suppression unitaire et par namespace
- Le rafraîchissement des documents indexés
- Le comptage et la vérification d'existence

### Responsabilités

1. **Indexation** : `index()`, `indexById()`, `indexAll()`
2. **Suppression** : `delete()`, `deleteById()`, `deleteAll()`
3. **Rafraîchissement** : `refresh()`, `refreshById()`, `reindexAll()`
4. **Interrogation** : `countIndexed()`, `exists()`, `existsById()`
5. **Configuration** : `setBatchSize()`, `setLimit()`

## Détails

[Voir la classe GenericIndexerInterface](https://github.com/andydefer/laravel-indexer/blob/main/src/Contracts/GenericIndexerInterface.php)

[Voir l'interface Indexable](https://github.com/andydefer/laravel-indexer/blob/main/src/Contracts/Indexable.php)

## API / Méthodes publiques

### `setBatchSize(int $batchSize): self`

Définit la taille des lots pour les opérations d'indexation en masse.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$batchSize` | `int` | Nombre de modèles à traiter par lot |

**Retourne :** `self` - L'instance courante pour le chaînage

**Exemple :**
```php
$service->setBatchSize(100)->indexAll(User::class);
```

---

### `setLimit(?int $limit): self`

Définit le nombre maximum de modèles à traiter.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$limit` | `int|null` | Nombre maximum de modèles, ou `null` pour illimité |

**Retourne :** `self` - L'instance courante pour le chaînage

**Exemple :**
```php
$service->setLimit(50)->indexAll(User::class);
// Indexe seulement les 50 premiers utilisateurs
```

---

### `index(Model&Indexable $model): void`

Indexe un modèle unique. Si déjà indexé, le rafraîchit.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model&Indexable` | Modèle à indexer |

**Retourne :** `void`

**Exemple :**
```php
$user = User::find(123);
$service->index($user);
```

---

### `indexById(string $modelClass, int $id): void`

Indexe un modèle par sa classe et son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |
| `$id` | `int` | ID du modèle |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
$service->indexById(User::class, 123);
```

---

### `indexAll(string $modelClass): void`

Indexe tous les modèles d'une classe donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |

**Retourne :** `void`

**Exemple :**
```php
$service->indexAll(User::class);
// Indexe tous les utilisateurs
```

---

### `reindexAll(string $modelClass): void`

Réindexe tous les modèles d'une classe (supprime puis indexe).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |

**Retourne :** `void`

**Exemple :**
```php
$service->reindexAll(User::class);
// Supprime tous les utilisateurs indexés puis les réindexe
```

---

### `delete(Model&Indexable $model): void`

Supprime un modèle de l'index.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model&Indexable` | Modèle à supprimer de l'index |

**Retourne :** `void`

**Exemple :**
```php
$user = User::find(123);
$service->delete($user);
```

---

### `deleteById(string $modelClass, int $id): void`

Supprime un modèle de l'index par sa classe et son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |
| `$id` | `int` | ID du modèle |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
$service->deleteById(User::class, 123);
```

---

### `deleteAll(string $modelClass): void`

Supprime tous les modèles d'une classe de l'index.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |

**Retourne :** `void`

**Exemple :**
```php
$service->deleteAll(User::class);
// Supprime tous les utilisateurs de l'index
```

---

### `refresh(Model&Indexable $model): void`

Rafraîchit un modèle dans l'index (supprime puis recrée).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model&Indexable` | Modèle à rafraîchir |

**Retourne :** `void`

**Comportement :**
1. Supprime le document existant
2. Si `shouldBeIndexed()` retourne `true`, recrée le document

**Exemple :**
```php
$user = User::find(123);
$service->refresh($user);
```

---

### `refreshById(string $modelClass, int $id): void`

Rafraîchit un modèle par sa classe et son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |
| `$id` | `int` | ID du modèle |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

---

### `countIndexed(string $modelClass): int`

Retourne le nombre de documents indexés pour une classe donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |

**Retourne :** `int` - Nombre de documents indexés

**Exemple :**
```php
$count = $service->countIndexed(User::class);
echo "{$count} utilisateurs indexés";
```

---

### `exists(Model&Indexable $model): bool`

Vérifie si un modèle est actuellement indexé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model&Indexable` | Modèle à vérifier |

**Retourne :** `bool` - `true` si le modèle est indexé

---

### `existsById(string $modelClass, int $id): bool`

Vérifie si un modèle est indexé par sa classe et son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |
| `$id` | `int` | ID du modèle |

**Retourne :** `bool` - `true` si le modèle est indexé

---

## Cas d'utilisation

### Cas 1 : Indexation d'un nouveau modèle

```php
class UserService
{
    public function __construct(
        private readonly GenericIndexerInterface $indexer
    ) {}

    public function create(array $data): User
    {
        $user = User::create($data);
        $this->indexer->index($user);
        return $user;
    }
}
```

### Cas 2 : Suppression avec nettoyage d'index

```php
class UserService
{
    public function delete(int $id): void
    {
        $user = User::findOrFail($id);
        $user->delete();
        $this->indexer->delete($user);
    }
}
```

### Cas 3 : Réindexation après mise à jour

```php
class UserService
{
    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        $this->indexer->refresh($user);
        return $user;
    }
}
```

### Cas 4 : Indexation initiale en masse

```php
class SetupCommand
{
    public function handle(): void
    {
        $this->indexer->setBatchSize(100);
        $this->indexer->indexAll(User::class);
        $this->indexer->indexAll(Product::class);
        $this->indexer->indexAll(Order::class);
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle non trouvé dans `indexById()` | `ModelNotFoundException` | `Model with ID {$id} not found` |
| Modèle non trouvé dans `deleteById()` | `ModelNotFoundException` | `Model with ID {$id} not found` |
| Modèle non trouvé dans `refreshById()` | `ModelNotFoundException` | `Model with ID {$id} not found` |

---

## Intégration

Ce service est le point d'entrée principal pour l'indexation. Il est utilisé par :

- **`GenericIndexModelsDirective`** : Pour les commandes CLI
- **`GenericOrchestratorRecurringTask`** : Pour les tâches récurrentes
- **`GenericIndexBatchUniqueTask`** : Pour les tâches par lots

### Flux de données

```
GenericIndexerService
    │
    ├── index()
    │   ├── shouldBeIndexed() → bool
    │   ├── getIndexableCluster() → ClusterVO
    │   ├── IndexableRecordFactory::convert() → IndexedDocumentRecord
    │   ├── existsByFingerPrint() → bool
    │   └── indexer->index() ou indexer->refresh()
    │
    ├── indexAll()
    │   ├── chunk() par batchSize
    │   ├── shouldBeIndexed() → bool
    │   ├── existsByFingerPrint() → bool
    │   ├── deleteByFingerPrint() (si existe)
    │   ├── IndexableRecordFactory::convert()
    │   └── indexer->indexMany()
    │
    └── refresh()
        ├── indexer->delete()
        ├── shouldBeIndexed() → bool
        └── indexer->refresh()
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `index()` | O(m) | m = nombre de tokens générés |
| `indexAll()` | O(n * m) | n = nombre de modèles, m = tokens par modèle |
| `indexAll()` avec `limit` | O(limit * m) | S'arrête après limit modèles |
| `deleteAll()` | O(1) | Suppression par namespace |

**Optimisations :**

- `indexAll()` utilise `chunk()` pour éviter la surcharge mémoire
- Les modèles déjà indexés sont supprimés avant réindexation
- `indexMany()` utilise le buffering pour les tokens

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

use AndyDefer\LaravelIndexer\Services\GenericIndexerService;
use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;

class IndexingService
{
    public function __construct(
        private readonly GenericIndexerInterface $indexer
    ) {}

    public function fullReindex(): void
    {
        // Configurer les lots
        $this->indexer->setBatchSize(50);

        // Réindexer tous les types
        $this->indexer->reindexAll(User::class);
        $this->indexer->reindexAll(Product::class);
        $this->indexer->reindexAll(Order::class);

        // Vérifier le résultat
        $count = $this->indexer->countIndexed(User::class);
        echo "Utilisateurs indexés: {$count}\n";
    }

    public function indexSpecificModels(array $ids): void
    {
        foreach ($ids as $id) {
            try {
                $this->indexer->indexById(User::class, $id);
            } catch (ModelNotFoundException $e) {
                echo "Utilisateur {$id} non trouvé\n";
            }
        }
    }

    public function cleanup(): void
    {
        // Supprimer les utilisateurs inactifs de l'index
        $inactiveUsers = User::where('active', false)->get();
        foreach ($inactiveUsers as $user) {
            $this->indexer->delete($user);
        }

        // Vérifier
        $count = $this->indexer->countIndexed(User::class);
        echo "Utilisateurs actifs indexés: {$count}\n";
    }
}
```

---

## Voir aussi

- `GenericIndexerInterface` - Interface du service
- `Indexable` - Interface des modèles indexables
- `IndexerInterface` - Service d'indexation sous-jacent
- `IndexableRecordFactory` - Factory de records
- `IndexableFingerprintVO` - Value Object de fingerprint
- `GenericIndexModelsDirective` - Directive CLI pour l'indexation