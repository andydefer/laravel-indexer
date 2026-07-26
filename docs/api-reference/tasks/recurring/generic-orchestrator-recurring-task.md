# GenericOrchestratorRecurringTask - Référence Technique

## Description

Tâche récurrente qui orchestre l'indexation en masse de tous les modèles configurés. Elle découpe les données en lots et dispatche des tâches uniques pour chaque lot, permettant une indexation parallélisée et résiliente.

## Hiérarchie / Implémentations

```
AbstractRecurringTask
    └── GenericOrchestratorRecurringTask
```

**Dépendances :**
- `IndexerConfigInterface` - Configuration des modèles à indexer
- `UniqueTaskServiceInterface` - Service de gestion des tâches uniques

**Lien source :** [GenericOrchestratorRecurringTask.php](https://github.com/andydefer/laravel-indexer/blob/main/src/Tasks/RecurringTasks/GenericOrchestratorRecurringTask.php)

## Rôle principal

Cette tâche récurrente est le **cerveau** de l'indexation automatique. Elle :

1. **Récupère** la liste des modèles à indexer depuis la configuration
2. **Découpe** les données en lots (batch) pour chaque modèle
3. **Dispatch** une tâche unique par lot via `GenericIndexBatchUniqueTask`
4. **Traite** les modèles éligibles uniquement (`shouldBeIndexed()`)

---

## Cycle de vie

```
Démarrage de la tâche récurrente
    ↓
Récupération des modèles configurés
    ↓
Pour chaque modèle :
    ↓
    Récupération des IDs éligibles (shouldBeIndexed)
    ↓
    Découpage en lots (batchSize)
    ↓
    Pour chaque lot :
        ↓
        Création d'une collection IndexableVO
        ↓
        Enregistrement d'une tâche GenericIndexBatchUniqueTask
    ↓
Fin de l'exécution
```

---

## Méthodes

### `process(): void`

Méthode principale exécutée lors de l'appel de la tâche récurrente.

**Retourne :** `void`

**Exemple :**
```php
// La tâche est exécutée automatiquement par le système de tâches récurrentes
// Exemple d'enregistrement :
$config = RecurringTaskConfigRecord::from([
    'interval_seconds' => new DurationVO(60), // Toutes les minutes
    'start_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
    'max_attempts' => new MaxFailedAttemptsVO(3),
]);

$recurringTaskService->register(
    new RecurringTaskFqcnVO(GenericOrchestratorRecurringTask::class),
    StrictDataObject::from(['enabled' => true]),
    $config
);
```

---

### `getModelChunks(string $modelClass, int $batchSize): array`

Découpe les IDs d'un modèle en lots de taille `$batchSize`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle |
| `$batchSize` | `int` | Taille des lots |

**Retourne :** `array<int, array<int>>` - Tableau de lots d'IDs

**Exemple :**
```php
$chunks = $this->getModelChunks(User::class, 50);
// Résultat : [[1,2,3,...50], [51,52,...100], ...]
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
        $this->info(new DescriptionVO('Indexation terminée avec succès'));
    } else {
        $this->error(new DescriptionVO("Échec de l'indexation : {$error->getValue()}"));
    }
}
```

---

## Cas d'utilisation

### Cas 1 : Configuration de l'indexation automatique

```php
// config/indexer.php
return [
    'batch_size' => 50,
    'model_indexables' => [
        App\Models\User::class,
        App\Models\Hospital::class,
        App\Models\Specialty::class,
    ],
];

// La tâche récurrente va automatiquement :
// - Indexer les utilisateurs par lots de 50
// - Indexer les hôpitaux par lots de 50
// - Indexer les spécialités par lots de 50
// - S'exécuter selon l'intervalle configuré
```

---

### Cas 2 : Enregistrement manuel de la tâche

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Tasks\RecurringTasks\GenericOrchestratorRecurringTask;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;

class IndexerSetup
{
    public function __construct(
        private readonly RecurringTaskServiceInterface $recurringTaskService
    ) {}

    public function setupOrchestrator(): void
    {
        $config = RecurringTaskConfigRecord::from([
            'interval_seconds' => new DurationVO(300), // Toutes les 5 minutes
            'start_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
            'max_attempts' => new MaxFailedAttemptsVO(5),
            'description' => 'Orchestrateur d\'indexation des modèles',
        ]);

        $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(GenericOrchestratorRecurringTask::class),
            StrictDataObject::from(['enabled' => true]),
            $config
        );
    }
}
```

---

### Cas 3 : Exécution manuelle pour test

```bash
# Exécuter la tâche récurrente manuellement
bin/afya tasks:process

# Sortie typique :
# INFO  Starting generic orchestrator: finding models to index...
# INFO  Processing App\Models\User...
# INFO  Dispatched 150 App\Models\User in 3 batches
# INFO  Processing App\Models\Hospital...
# INFO  Dispatched 80 App\Models\Hospital in 2 batches
# INFO  Orchestrator completed: 230 items dispatched in 5 batch tasks
# INFO  Generic orchestrator task completed successfully
```

---

## Structure du payload

La tâche récurrente crée des payloads pour les tâches uniques :

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
        // ... jusqu'à batchSize
    ]
}
```

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Aucun modèle configuré | La tâche s'exécute sans erreur, aucun lot n'est dispatché |
| Modèle sans données | La tâche s'exécute, aucun lot n'est dispatché pour ce modèle |
| Échec du dispatch d'une tâche unique | L'erreur est loggée, mais l'orchestrateur continue |
| Échec complet | Le hook `after()` est appelé avec `$success = false` |

---

## Intégration

### Avec IndexerConfig

Récupération des modèles configurés :

```php
$modelClasses = $indexerConfig->getModelIndexables();
```

### Avec UniqueTaskService

Dispatch des tâches uniques :

```php
$uniqueTaskService->register(
    new UniqueTaskFqcnVO(GenericIndexBatchUniqueTask::class),
    $payload,
    $config
);
```

### Avec GenericIndexBatchUniqueTask

La tâche uniques traite chaque lot :

```php
// GenericIndexBatchUniqueTask reçoit une collection d'IndexableVO
// et les indexe avec leurs clusters dynamiques respectifs
```

---

## Performance

- **Batch processing** : Traitement par lots pour éviter les surcharges mémoire
- **Parallélisation** : Les lots sont dispatchés en tant que tâches uniques indépendantes
- **Scalabilité** : Peut gérer des millions d'enregistrements grâce au chunking
- **Complexité** : O(n) où n est le nombre total d'éléments à indexer

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

use AndyDefer\LaravelIndexer\Tasks\RecurringTasks\GenericOrchestratorRecurringTask;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;

class OrchestratorSetup
{
    public function __construct(
        private readonly RecurringTaskServiceInterface $recurringTaskService
    ) {}

    public function setup(): void
    {
        // 1. Configurer l'intervalle
        $config = RecurringTaskConfigRecord::from([
            'interval_seconds' => new DurationVO(60), // Toutes les minutes
            'start_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
            'max_attempts' => new MaxFailedAttemptsVO(3),
            'description' => 'Orchestrateur d\'indexation',
        ]);

        // 2. Enregistrer la tâche récurrente
        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(GenericOrchestratorRecurringTask::class),
            StrictDataObject::from(['enabled' => true]),
            $config
        );

        echo "✅ Orchestrateur enregistré : {$alias->getValue()}\n";
    }

    public function getStatus(): void
    {
        $tasks = $this->recurringTaskService->findPending();
        echo "📊 Tâches récurrentes en attente : {$tasks->count()}\n";
    }

    public function pauseOrchestrator(string $alias): void
    {
        $this->recurringTaskService->pause(new TaskAliasVO($alias));
        echo "⏸️ Orchestrateur mis en pause\n";
    }

    public function resumeOrchestrator(string $alias): void
    {
        $this->recurringTaskService->resume(new TaskAliasVO($alias));
        echo "▶️ Orchestrateur repris\n";
    }

    public function cancelOrchestrator(string $alias): void
    {
        $this->recurringTaskService->cancel(new TaskAliasVO($alias));
        echo "❌ Orchestrateur annulé\n";
    }
}
```

---

## Voir aussi

- `GenericIndexBatchUniqueTask` - Tâche unique pour l'indexation par lots
- `AbstractRecurringTask` - Classe de base pour les tâches récurrentes
- `IndexerConfig` - Configuration des modèles à indexer
- `UniqueTaskService` - Service de gestion des tâches uniques
- `RecurringTaskService` - Service de gestion des tâches récurrentes
- `TasksProcessDirective` - Directive pour exécuter les tâches