# GenericIndexBatchUniqueTask - Référence Technique

## Description

Tâche unique qui traite un lot d'éléments à indexer. Elle reçoit une collection d'`IndexableVO` et indexe chaque modèle avec son cluster dynamique.

## Hiérarchie / Implémentations

```
AbstractUniqueTask
    └── GenericIndexBatchUniqueTask
```

**Dépendances :**
- `IndexerInterface` - Service d'indexation bas niveau
- `IndexedDocumentRepositoryInterface` - Repository des documents
- `ConsoleInterface` - Interface console pour les alertes

**Lien source :** [GenericIndexBatchUniqueTask.php](https://github.com/andydefer/laravel-indexer/blob/main/src/Tasks/UniqueTasks/GenericIndexBatchUniqueTask.php)

## Rôle principal

Cette tâche unique est le **travailleur** de l'indexation. Elle :

1. **Reçoit** une collection d'`IndexableVO` (modèle + ID)
2. **Récupère** chaque modèle depuis la base de données
3. **Vérifie** l'éligibilité du modèle (`shouldBeIndexed()`)
4. **Supprime** le document s'il existe déjà (réindexation)
5. **Indexe** le modèle avec son cluster dynamique
6. **Gère** les erreurs (modèle introuvable)

---

## Cycle de vie

```
Réception du payload
    ↓
Vérification de la collection (items)
    ↓
Pour chaque item :
    ↓
    Récupération du modèle via getInstance()
    ↓
    Vérification de l'éligibilité (shouldBeIndexed)
    ↓
    Si déjà indexé → suppression
    ↓
    Indexation avec cluster dynamique
    ↓
Résumé des résultats
```

---

## Structure du payload

```json
{
    "items": [
        {
            "modelClass": "App\\Models\\User",
            "id": 1
        },
        {
            "modelClass": "App\\Models\\User",
            "id": 2
        }
    ]
}
```

---

## Méthodes

### `process(): void`

Méthode principale exécutée lors de l'appel de la tâche.

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Capturée pour chaque item

**Exemple :**
```php
// La tâche est exécutée automatiquement par le système de tâches
// Exemple d'enregistrement :
$config = UniqueTaskConfigRecord::from([
    'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
    'max_attempts' => new MaxFailedAttemptsVO(3),
]);

$uniqueTaskService->register(
    new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
    $payload,
    $config
);
```

---

### `after(bool $success, ?DescriptionVO $error): void`

Hook exécuté après le traitement de la tâche.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si la tâche a réussi |
| `$error` | `DescriptionVO|null` | Message d'erreur en cas d'échec |

**Retourne :** `void`

**Exemple :**
```php
protected function after(bool $success, ?DescriptionVO $error = null): void
{
    if ($success) {
        $this->info(new DescriptionVO('Lot indexé avec succès'));
    } else {
        $this->error(new DescriptionVO("Échec de l'indexation du lot : {$error->getValue()}"));
    }
}
```

---

## Cas d'utilisation

### Cas 1 : Indexation d'un lot d'utilisateurs

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;

class UserIndexer
{
    public function __construct(
        private readonly UniqueTaskServiceInterface $uniqueTaskService
    ) {}

    public function indexUserBatch(array $userIds): void
    {
        $collection = new IndexableVOCollection;
        foreach ($userIds as $id) {
            $collection->add(new IndexableVO(User::class, $id));
        }

        $payload = StrictDataObject::from([
            'items' => $collection,
        ]);

        $config = UniqueTaskConfigRecord::from([
            'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
            'max_attempts' => new MaxFailedAttemptsVO(3),
        ]);

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
            $payload,
            $config
        );
    }
}
```

---

### Cas 2 : Gestion des modèles non éligibles

```php
// Exemple de log lorsqu'un modèle n'est pas éligible
// Item 5 should not be indexed, skipping

// Causes possibles :
// - Le modèle est inactif (is_active = false)
// - La méthode shouldBeIndexed() retourne false
// - Le modèle n'est pas publié (is_published = false)
```

---

### Cas 3 : Réindexation d'un lot existant

```php
// Si un document existe déjà, il est supprimé puis réindexé
// Item 42 already indexed, deleting and re-indexing

// Cela garantit que l'index est toujours à jour
// avec les dernières données du modèle
```

---

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Payload invalide (pas d'items) | Erreur et arrêt | `Invalid payload: missing items or empty collection` |
| Collection vide | Erreur et arrêt | `Invalid payload: items collection is empty` |
| Modèle introuvable | Skip + alerte | `Item {id} not found, skipping` |
| Modèle non éligible | Skip + info | `Item {id} should not be indexed, skipping` |
| Échec global | Hook `after()` avec `$success = false` | `Batch indexation failed: {error}` |

---

## Intégration

### Avec GenericOrchestratorRecurringTask

La tâche est dispatchée par l'orchestrateur :

```php
// GenericOrchestratorRecurringTask crée les payloads
$payload = StrictDataObject::from([
    'items' => $collection, // IndexableVOCollection
]);

$uniqueTaskService->register(
    new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
    $payload,
    $config
);
```

### Avec IndexableVO

Chaque item est un `IndexableVO` :

```php
public function getInstance(): Model&Indexable
{
    return $this->modelClass::find($this->id);
}
```

### Avec IndexableRecordFactory

Conversion du modèle en `IndexableRecord` :

```php
$cluster = $model->getIndexableCluster();
$record = IndexableRecordFactory::convert($model, $cluster);
```

---

## Performance

- **Batch processing** : Traite plusieurs éléments en une seule tâche
- **Suppression des doublons** : Supprime avant d'indexer pour éviter les conflits
- **Gestion des erreurs** : Continue même si un élément échoue
- **Complexité** : O(n) où n est le nombre d'éléments dans le lot

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

use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;
use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;

class BatchIndexer
{
    public function __construct(
        private readonly UniqueTaskServiceInterface $uniqueTaskService
    ) {}

    public function indexBatch(string $modelClass, array $ids): void
    {
        // 1. Créer la collection
        $collection = new IndexableVOCollection;
        foreach ($ids as $id) {
            $collection->add(new IndexableVO($modelClass, $id));
        }

        // 2. Créer le payload
        $payload = StrictDataObject::from([
            'items' => $collection,
        ]);

        // 3. Configurer la tâche
        $config = UniqueTaskConfigRecord::from([
            'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
            'max_attempts' => new MaxFailedAttemptsVO(3),
            'description' => "Indexation du lot de {$modelClass}",
        ]);

        // 4. Enregistrer la tâche
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
            $payload,
            $config
        );

        echo "✅ Tâche enregistrée : {$alias->getValue()}\n";
    }

    public function processBatch(array $ids): void
    {
        $this->indexBatch(User::class, $ids);
    }

    public function processBatches(array $allIds, int $batchSize = 50): void
    {
        $chunks = array_chunk($allIds, $batchSize);
        foreach ($chunks as $chunk) {
            $this->indexBatch(User::class, $chunk);
        }
        echo "✅ {$totalBatches} lots dispatchés\n";
    }

    public function getTaskStatus(string $alias): void
    {
        $task = $this->uniqueTaskService->find(new TaskAliasVO($alias));
        if ($task) {
            echo "📊 Statut : {$task->status->value}\n";
            echo "📊 Tentatives : {$task->attempts->getValue()}/{$task->max_attempts->getValue()}\n";
        }
    }
}
```

---

## Voir aussi

- `GenericOrchestratorRecurringTask` - Tâche récurrente qui dispatch cette tâche
- `AbstractUniqueTask` - Classe de base pour les tâches uniques
- `IndexableVO` - Value Object pour les modèles indexables
- `IndexableVOCollection` - Collection d'IndexableVO
- `UniqueTaskService` - Service de gestion des tâches uniques
- `IndexableRecordFactory` - Factory pour les enregistrements indexables