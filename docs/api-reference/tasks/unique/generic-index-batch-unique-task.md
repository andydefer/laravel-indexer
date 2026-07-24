# GenericIndexBatchUniqueTask - Référence Technique

## Description

Cette tâche unique indexe un lot d'éléments (modèles Eloquent) dans le système d'indexation. Elle est conçue pour être utilisée par l'orchestrateur générique qui découpe les données en lots pour une indexation optimisée.

## Hiérarchie / Implémentations

```
AbstractUniqueTask
    └── GenericIndexBatchUniqueTask
```

**Interfaces implémentées :** Hérite des capacités de `AbstractUniqueTask` (exécution unique, gestion des tentatives, journalisation)

## Rôle principal

La tâche reçoit un payload contenant un objet `IndexableVO` (définissant le modèle et son cluster) et une liste d'IDs. Elle récupère les modèles correspondants, vérifie s'ils doivent être indexés (`shouldBeIndexed()`), supprime les documents existants en cas de réindexation, puis les indexe en lot via `IndexerInterface`.

---

## API / Méthodes publiques

### `process(): void`

Méthode principale d'exécution de la tâche.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | Utilise le payload du contexte de la tâche |

**Retourne :** `void`

**Exceptions :** Aucune exception directe (les erreurs sont gérées via les logs)

**Exemple :**
```php
// La tâche est exécutée automatiquement par le système de tâches
// Le payload est extrait du contexte
$payload = $this->context->getPayload();
$indexableVO = IndexableVO::from($payload->indexable);
$ids = $payload->ids;
```

---

### `after(bool $success, ?DescriptionVO $error = null): void`

Hook d'après-exécution appelé automatiquement après `process()`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si la tâche s'est terminée avec succès |
| `$error` | `?DescriptionVO` | Description de l'erreur en cas d'échec |

**Retourne :** `void`

**Exemple :**
```php
protected function after(bool $success, ?DescriptionVO $error = null): void
{
    if ($success) {
        $this->info(new DescriptionVO('Batch indexation completed successfully'));
    } else {
        $this->error(new DescriptionVO("Batch indexation failed: {$error->getValue()}"));
    }
}
```

---

## Cas d'utilisation

### Cas 1 : Indexation d'un lot de médecins

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;

/** @var UniqueTaskServiceInterface $uniqueTaskService */
$uniqueTaskService = app(UniqueTaskServiceInterface::class);

$indexableVO = new IndexableVO(
    modelClass: App\Models\Doctor::class,
    cluster: new ClusterVO('type:doctor|status:active')
);

$config = UniqueTaskConfigRecord::from([
    'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
    'max_attempts' => new MaxFailedAttemptsVO(3),
    'grace_period' => new DurationVO(3600),
    'description' => new DescriptionVO('Batch task for indexing doctors'),
]);

$payload = StrictDataObject::from([
    'indexable' => $indexableVO,
    'ids' => [1, 2, 3, 4, 5],
]);

$uniqueTaskService->register(
    new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
    $payload,
    $config
);
```

### Cas 2 : Réindexation automatique avec suppression préalable

Lorsqu'un élément est déjà indexé, la tâche le supprime avant de le réindexer :

```php
// Dans le processus de la tâche
$fingerPrint = IndexableFingerPrintVO::fromParts(
    $model->getMorphClass(),
    (string) $model->getKey()
);

if ($documentRepository->existsByFingerPrint($fingerPrint)) {
    $this->info(new DescriptionVO("Item {$id} already indexed, deleting and re-indexing"));
    $documentRepository->deleteByFingerPrint($fingerPrint);
}

// Puis indexation
$cluster = new ClusterVO($indexableVO->getClusterType());
$records->add(IndexableRecordFactory::convert($model, $cluster));
```

### Cas 3 : Ignorer les éléments non indexables

```php
// Les éléments qui ne doivent pas être indexés sont ignorés
if (! $model->shouldBeIndexed()) {
    $this->info(new DescriptionVO("Item {$id} should not be indexed, skipping"));
    $skipped++;
    continue;
}
```

---

## Gestion des erreurs

| Situation | Exception / Comportement | Message |
|-----------|--------------------------|---------|
| Payload invalide (absence de `indexable` ou `ids`) | Log d'erreur, arrêt de la tâche | `Invalid payload: missing indexable or ids` |
| Élément non trouvé en base | Log d'avertissement, skip de l'élément | `Item {$id} not found, skipping` |
| Élément non indexable | Log d'information, skip de l'élément | `Item {$id} should not be indexed, skipping` |
| Échec de l'indexation | Log d'erreur via le hook `after()` | `Batch indexation failed: {$error->getValue()}` |

---

## Intégration

### Dépendances injectées via le conteneur Laravel

| Service | Interface | Rôle |
|---------|-----------|------|
| `IndexerInterface` | `AndyDefer\LaravelIndexer\Contracts\IndexerInterface` | Indexation des documents |
| `IndexedDocumentRepositoryInterface` | `AndyDefer\LaravelIndexer\Contracts\IndexedDocumentRepositoryInterface` | Gestion des documents indexés |
| `ConsoleInterface` | `AndyDefer\ConsoleWriter\Console\Contracts\ConsoleInterface` | Affichage des alertes console |

### Appelée par

- **`GenericOrchestratorRecurringTask`** : Orchestrateur qui crée les tâches batch
- **Manuellement** : Via le système de tâches ou les directives

### Utilisée dans

- **`GenericIndexModelsDirective`** : Directive de ligne de commande
- **Système de tâches récurrentes** : Exécution automatique planifiée

---

## Performance

### Complexité

- **Temps** : O(n) où n est le nombre d'éléments dans le lot
- **Mémoire** : O(n) - charge tous les éléments du lot en mémoire

### Optimisations

- **Traitement par lots** : Les éléments sont indexés en une seule opération via `indexMany()`
- **Déduplication** : Vérification et suppression des doublons avant indexation
- **Filtrage précoce** : Les éléments non indexables sont ignorés avant l'indexation

### Recommandations

| Cas | Batch size recommandé |
|-----|----------------------|
| Modèles légers (peu de champs) | 100-200 |
| Modèles lourds (beaucoup de données) | 50-100 |
| Indexation initiale | 100-200 |

**Configuration :**
```php
// config/indexer.php
'batch_size' => env('INDEXER_BATCH_SIZE', 50),
```

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| Laravel 12-15 | ✅ Complet |

**Dépendances :**
- `andydefer/laravel-indexer` (package parent)
- `andydefer/laravel-task` ^4.10
- `andydefer/console-writer` ^1.6
- `illuminate/database` (Eloquent)

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Tasks\UniqueTasks\GenericIndexBatchUniqueTask;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use App\Models\Doctor;

// 1. Créer l'objet IndexableVO
$indexableVO = new IndexableVO(
    modelClass: Doctor::class,
    cluster: new ClusterVO('type:doctor|status:active')
);

// 2. Récupérer les IDs à indexer (par exemple, les médecins actifs)
$ids = Doctor::where('is_active', true)->pluck('id')->toArray();

// 3. Créer la configuration de la tâche
$config = UniqueTaskConfigRecord::from([
    'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
    'max_attempts' => new MaxFailedAttemptsVO(3),
    'grace_period' => new DurationVO(3600),
    'description' => new DescriptionVO('Index doctors batch'),
]);

// 4. Créer le payload
$payload = StrictDataObject::from([
    'indexable' => $indexableVO,
    'ids' => $ids,
]);

// 5. Enregistrer la tâche
$uniqueTaskService = app(UniqueTaskServiceInterface::class);
$uniqueTaskService->register(
    new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
    $payload,
    $config
);

// 6. La tâche sera exécutée automatiquement par le système de tâches
// ou manuellement via : ./vendor/bin/directive tasks:process
```

---

## Voir aussi

- `GenericOrchestratorRecurringTask` - Orchestrateur qui crée les tâches batch
- `AbstractUniqueTask` - Classe parente des tâches uniques
- `IndexableVO` - Value Object définissant le modèle et son cluster
- `IndexableRecordFactory` - Factory pour créer des records indexables