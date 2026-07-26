# GenericIndexerService - Référence Technique

## Description

Service générique d'indexation qui gère le cycle de vie complet des documents indexés pour n'importe quel modèle Eloquent implémentant l'interface `Indexable`. Il utilise des clusters dynamiques générés par les modèles eux-mêmes.

## Hiérarchie / Implémentations

```
GenericIndexerService
    └── Implémente GenericIndexerInterface
```

**Dépendances :**
- `IndexerInterface` - Service d'indexation bas niveau
- `IndexedDocumentRepositoryInterface` - Repository des documents
- `IndexerConfigInterface` - Configuration de l'indexeur

**Lien source :** [GenericIndexerService.php](https://github.com/andydefer/laravel-indexer/blob/main/src/Services/GenericIndexerService.php)

## Rôle principal

Orchestre les opérations d'indexation pour les modèles Eloquent :
- **Indexation** : Ajout ou mise à jour de documents
- **Suppression** : Retrait de documents de l'index
- **Réindexation** : Reconstruction complète de l'index
- **Vérification** : Existence et comptage des documents
- **Gestion des lots** : Traitement par batch avec limitation

---

## API / Méthodes publiques

### `setBatchSize(int $batchSize): self`

Définit la taille des lots pour l'indexation en masse.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$batchSize` | `int` | Nombre d'éléments par lot |

**Retourne :** `self` - Instance du service (fluent)

**Exemple :**
```php
$genericIndexer->setBatchSize(100)->indexAll(User::class);
```

---

### `setLimit(?int $limit): self`

Définit le nombre maximum d'éléments à indexer.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$limit` | `int|null` | Nombre maximum d'éléments, ou `null` pour illimité |

**Retourne :** `self` - Instance du service (fluent)

**Exemple :**
```php
$genericIndexer->setLimit(500)->indexAll(User::class);
```

---

### `index(Model&Indexable $model): void`

Indexe un modèle spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model&Indexable` | Le modèle à indexer |

**Retourne :** `void`

**Exemple :**
```php
$user = User::find(1);
$genericIndexer->index($user);
```

---

### `indexById(string $modelClass, int $id): void`

Indexe un modèle par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |
| `$id` | `int` | ID du modèle |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
$genericIndexer->indexById(User::class, 42);
```

---

### `indexAll(string $modelClass): void`

Indexe tous les modèles d'une classe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->indexAll(User::class);
```

---

### `reindexAll(string $modelClass): void`

Réindexe tous les modèles d'une classe (supprime puis réindexe).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->reindexAll(User::class);
```

---

### `delete(Model&Indexable $model): void`

Supprime un modèle de l'index.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model&Indexable` | Le modèle à supprimer |

**Retourne :** `void`

**Exemple :**
```php
$user = User::find(1);
$genericIndexer->delete($user);
```

---

### `deleteById(string $modelClass, int $id): void`

Supprime un modèle de l'index par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |
| `$id` | `int` | ID du modèle |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
$genericIndexer->deleteById(User::class, 42);
```

---

### `deleteAll(string $modelClass): void`

Supprime tous les documents d'une classe de l'index.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->deleteAll(User::class);
```

---

### `refresh(Model&Indexable $model): void`

Rafraîchit un modèle dans l'index (supprime puis réindexe si éligible).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model&Indexable` | Le modèle à rafraîchir |

**Retourne :** `void`

**Exemple :**
```php
$user = User::find(1);
$genericIndexer->refresh($user);
```

---

### `refreshById(string $modelClass, int $id): void`

Rafraîchit un modèle par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |
| `$id` | `int` | ID du modèle |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle n'existe pas

**Exemple :**
```php
$genericIndexer->refreshById(User::class, 42);
```

---

### `countIndexed(string $modelClass): int`

Compte les documents indexés pour une classe de modèle.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |

**Retourne :** `int` - Nombre de documents indexés

**Exemple :**
```php
$count = $genericIndexer->countIndexed(User::class);
echo "{$count} utilisateurs indexés";
```

---

### `exists(Model&Indexable $model): bool`

Vérifie si un modèle est indexé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model&Indexable` | Le modèle à vérifier |

**Retourne :** `bool` - `true` si le modèle est indexé

**Exemple :**
```php
$user = User::find(1);
if ($genericIndexer->exists($user)) {
    echo "L'utilisateur est indexé";
}
```

---

### `existsById(string $modelClass, int $id): bool`

Vérifie si un modèle est indexé par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |
| `$id` | `int` | ID du modèle |

**Retourne :** `bool` - `true` si le modèle est indexé

**Exemple :**
```php
if ($genericIndexer->existsById(User::class, 42)) {
    echo "L'utilisateur 42 est indexé";
}
```

---

## Cas d'utilisation

### Cas 1 : Indexation d'un nouvel utilisateur

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;

class UserService
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer
    ) {}

    public function createUser(array $data): User
    {
        $user = User::create($data);
        
        // ✅ Indexer automatiquement le nouvel utilisateur
        $this->genericIndexer->index($user);
        
        return $user;
    }
}
```

---

### Cas 2 : Réindexation complète après une migration

```php
<?php

declare(strict_types=1);

class ReindexCommand
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer
    ) {}

    public function handle(): void
    {
        // ✅ Réindexer tous les médecins
        $this->genericIndexer
            ->setBatchSize(100)
            ->setLimit(null)
            ->reindexAll(Doctor::class);
        
        // ✅ Réindexer tous les hôpitaux
        $this->genericIndexer
            ->setBatchSize(50)
            ->reindexAll(Hospital::class);
        
        echo "✅ Réindexation terminée\n";
    }
}
```

---

### Cas 3 : Suppression en cascade

```php
<?php

declare(strict_types=1);

class UserDeletionService
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer
    ) {}

    public function deleteUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        
        // ✅ Supprimer l'utilisateur de l'index
        $this->genericIndexer->delete($user);
        
        // ✅ Supprimer l'utilisateur de la base de données
        $user->delete();
    }
}
```

---

### Cas 4 : Vérification et mise à jour

```php
<?php

declare(strict_types=1);

class IndexVerificationService
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer
    ) {}

    public function verifyAndFixIndex(): void
    {
        $users = User::where('is_active', true)->get();
        $missing = 0;
        
        foreach ($users as $user) {
            if (! $this->genericIndexer->exists($user)) {
                $this->genericIndexer->index($user);
                $missing++;
            }
        }
        
        echo "✅ {$missing} utilisateurs manquants réindexés\n";
        
        $total = $this->genericIndexer->countIndexed(User::class);
        echo "📊 Total indexé : {$total}\n";
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle introuvable (indexById) | `ModelNotFoundException` | `Model with ID {id} not found` |
| Modèle introuvable (deleteById) | `ModelNotFoundException` | `Model with ID {id} not found` |
| Modèle introuvable (refreshById) | `ModelNotFoundException` | `Model with ID {id} not found` |

---

## Intégration

### Avec Indexable

Chaque modèle doit implémenter l'interface `Indexable` :

```php
class User extends Model implements Indexable
{
    public function shouldBeIndexed(): bool { ... }
    public function getIndexableData(): StrictAssociative { ... }
    public function getIndexableCluster(): ClusterVO { ... }
    public function getKey() { ... }
    public function getMorphClass() { ... }
}
```

### Avec IndexableRecordFactory

La méthode `convert()` transforme un modèle en `IndexableRecord` :

```php
$record = IndexableRecordFactory::convert($model, $cluster);
```

### Avec IndexerInterface

Les opérations bas niveau sont déléguées à `IndexerInterface` :

```php
$this->indexer->index($record);
$this->indexer->indexMany($records);
$this->indexer->refresh($record);
$this->indexer->delete($fingerPrint);
```

### Avec IndexedDocumentRepositoryInterface

La gestion des documents est déléguée au repository :

```php
$this->documentRepository->existsByFingerPrint($fingerPrint);
$this->documentRepository->deleteByFingerPrint($fingerPrint);
$this->documentRepository->deleteByNamespace($namespace);
$this->documentRepository->countByNamespace($namespace);
```

---

## Performance

- **Batch processing** : Utilisation de `chunk()` pour éviter les surcharges mémoire
- **Limitation** : Possibilité de limiter le nombre d'éléments traités
- **Complexité** : O(n) où n est le nombre de modèles à indexer
- **Mémoire** : Les opérations en masse utilisent des collections pour minimiser l'empreinte mémoire

---

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |
| PHP 8.4+ | ✅ Complet |
| PHP 8.5+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;

class DoctorIndexer
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer
    ) {}

    public function indexDoctor(int $doctorId): void
    {
        // ✅ Indexer un médecin spécifique
        $this->genericIndexer->indexById(Doctor::class, $doctorId);
    }

    public function reindexAllDoctors(): void
    {
        // ✅ Réindexer tous les médecins avec des paramètres optimisés
        $this->genericIndexer
            ->setBatchSize(50)
            ->setLimit(null)
            ->reindexAll(Doctor::class);
    }

    public function getDoctorIndexCount(): int
    {
        // ✅ Compter les médecins indexés
        return $this->genericIndexer->countIndexed(Doctor::class);
    }

    public function isDoctorIndexed(int $doctorId): bool
    {
        // ✅ Vérifier si un médecin est indexé
        return $this->genericIndexer->existsById(Doctor::class, $doctorId);
    }

    public function deleteDoctorIndex(int $doctorId): void
    {
        // ✅ Supprimer un médecin de l'index
        $this->genericIndexer->deleteById(Doctor::class, $doctorId);
    }

    public function cleanupAllDoctors(): void
    {
        // ✅ Supprimer tous les médecins de l'index
        $this->genericIndexer->deleteAll(Doctor::class);
    }

    public function refreshDoctorIndex(int $doctorId): void
    {
        // ✅ Rafraîchir un médecin dans l'index
        $this->genericIndexer->refreshById(Doctor::class, $doctorId);
    }

    public function fullReindexWithProgress(): void
    {
        $before = $this->genericIndexer->countIndexed(Doctor::class);
        
        $this->genericIndexer
            ->setBatchSize(100)
            ->reindexAll(Doctor::class);
        
        $after = $this->genericIndexer->countIndexed(Doctor::class);
        
        echo "✅ Réindexation terminée : {$before} → {$after}\n";
    }
}
```

---

## Voir aussi

- `GenericIndexerInterface` - Interface du service
- `Indexable` - Interface pour les modèles indexables
- `IndexableRecordFactory` - Factory pour les enregistrements indexables
- `ClusterVO` - Value Object pour les clusters
- `IndexerConfig` - Configuration de l'indexeur
- `GenericIndexModelsDirective` - Directive CLI pour l'indexation