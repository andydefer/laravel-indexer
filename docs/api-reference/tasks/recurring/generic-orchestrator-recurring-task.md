# GenericOrchestratorRecurringTask - Référence Technique

## Description

Cette tâche récurrente agit comme un orchestrateur générique pour l'indexation des modèles Eloquent. Elle parcourt la liste des modèles configurés dans `indexer.model_indexables`, découpe les données en lots de taille configurable, et crée des tâches uniques (`GenericIndexBatchUniqueTask`) pour chaque lot.

## Hiérarchie / Implémentations

```
AbstractRecurringTask
    └── GenericOrchestratorRecurringTask
```

**Interfaces implémentées :** Hérite des capacités de `AbstractRecurringTask` (exécution périodique, gestion d'état, journalisation)

## Rôle principal

L'orchestrateur générique automatise le processus d'indexation en continu. Il lit la configuration des modèles à indexer, découpe les données en lots selon la taille configurée, et délègue l'indexation proprement dite à des tâches uniques. Cela permet une indexation progressive sans bloquer le système.

---

## API / Méthodes publiques

### `process(): void`

Méthode principale d'exécution de la tâche récurrente.

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | Utilise le contexte de la tâche pour accéder aux services |

**Retourne :** `void`

**Exceptions :** Aucune exception directe (les erreurs sont gérées via les logs)

**Exemple :**
```php
// La tâche est exécutée automatiquement par le système de tâches récurrentes
// Elle lit la configuration et crée des tâches batch
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
        $this->info(new DescriptionVO('Generic orchestrator task completed successfully'));
    } else {
        $this->error(new DescriptionVO("Generic orchestrator task failed: {$error->getValue()}"));
    }
}
```

---

### `getModelChunks(string $modelClass, int $batchSize): array`

Méthode privée qui découpe les IDs d'un modèle en lots.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle Eloquent |
| `$batchSize` | `int` | Taille maximale de chaque lot |

**Retourne :** `array<int, array<int>>` - Tableau de lots d'IDs

**Exemple :**
```php
$chunks = $this->getModelChunks(Doctor::class, 50);
// Résultat : [[1,2,3,...50], [51,52,...100], ...]
```

---

## Cas d'utilisation

### Cas 1 : Indexation automatique des modèles configurés

L'orchestrateur indexe automatiquement tous les modèles déclarés dans la configuration :

```php
// config/indexer.php
'model_indexables' => [
    App\Models\Doctor::class => 'type:doctor|status:active',
    App\Models\Pharmacy::class => 'type:pharmacy|status:active',
    App\Models\Product::class => 'type:product|status:published',
],
```

L'exécution de la tâche créera des tâches batch pour chaque modèle, avec les clusters correspondants.

### Cas 2 : Indexation progressive par lots

Avec une configuration de batch size à 50, l'orchestrateur découpe 150 médecins en 3 lots :

```php
// Configuration
'batch_size' => 50,

// Résultat : 3 tâches batch créées
// Lot 1 : IDs 1-50
// Lot 2 : IDs 51-100
// Lot 3 : IDs 101-150
```

### Cas 3 : Filtrage des éléments non indexables

Seuls les éléments qui retournent `true` à `shouldBeIndexed()` sont inclus dans les lots :

```php
// Dans le modèle
public function shouldBeIndexed(): bool
{
    return $this->is_active && $this->status === 'active';
}

// L'orchestrateur ne prendra que les éléments actifs
```

---

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Aucun modèle configuré | Log d'information | `Orchestrator completed: 0 items dispatched in 0 batch tasks` |
| Erreur lors de l'enregistrement d'une tâche batch | Log d'erreur via le hook `after()` | `Generic orchestrator task failed: {$error->getValue()}` |
| Modèle sans éléments indexables | Log d'information | `Dispatched 0 Model in 0 batches` |

---

## Intégration

### Dépendances injectées via le conteneur Laravel

| Service | Interface | Rôle |
|---------|-----------|------|
| `IndexerConfigInterface` | `AndyDefer\LaravelIndexer\Contracts\Configs\IndexerConfigInterface` | Configuration de l'indexeur |
| `UniqueTaskServiceInterface` | `AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface` | Enregistrement des tâches uniques |

### Configuration requise

```php
// config/indexer.php
return [
    'batch_size' => 50,
    'model_indexables' => [
        App\Models\User::class => 'type:user|role:doctor',
        App\Models\Hospital::class => 'type:hospital|status:active',
    ],
];
```

### Tâches créées par l'orchestrateur

| Type | Classe | Description |
|------|--------|-------------|
| Unique | `GenericIndexBatchUniqueTask` | Indexe un lot d'éléments |

### Appelée par

- **Système de tâches récurrentes** : Exécution automatique planifiée
- **Manuellement** : Via la directive `tasks:process`

---

## Performance

### Complexité

- **Temps** : O(n) où n est le nombre total d'éléments à indexer
- **Mémoire** : O(batchSize) - traite par lots pour limiter l'empreinte mémoire

### Optimisations

- **Chunking** : Utilise `chunk()` d'Eloquent pour éviter de charger tous les éléments en mémoire
- **Traitement asynchrone** : Délègue l'indexation à des tâches uniques qui s'exécutent en arrière-plan
- **Filtrage précoce** : Ne prend que les éléments `shouldBeIndexed()` avant de créer les lots

### Métriques de référence

| Nombre d'éléments | Batch size | Nombre de tâches | Temps estimé |
|-------------------|------------|------------------|--------------|
| 1 000 | 50 | 20 | ~2-5 secondes |
| 10 000 | 100 | 100 | ~20-30 secondes |
| 100 000 | 200 | 500 | ~3-5 minutes |

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
- `andydefer/domain-structures` ^1.24
- `illuminate/database` (Eloquent)

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Tasks\RecurringTasks\GenericOrchestratorRecurringTask;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;

// 1. Configuration des modèles à indexer
// config/indexer.php
'model_indexables' => [
    App\Models\Doctor::class => 'type:doctor|status:active',
    App\Models\Pharmacy::class => 'type:pharmacy|status:active',
    App\Models\Product::class => 'type:product|status:published',
],
'batch_size' => 100,

// 2. Enregistrement de la tâche récurrente
/** @var RecurringTaskServiceInterface $recurringTaskService */
$recurringTaskService = app(RecurringTaskServiceInterface::class);

$startAt = Carbon::now()->toIso8601String();

$config = RecurringTaskConfigRecord::from([
    'interval_seconds' => new DurationVO(60), // Toutes les minutes
    'start_at' => new Iso8601DateTimeVO($startAt),
    'max_attempts' => new MaxFailedAttemptsVO(3),
    'description' => new DescriptionVO('Generic orchestrator for indexing models'),
]);

$recurringTaskService->register(
    new RecurringTaskFqcnVO(GenericOrchestratorRecurringTask::class),
    StrictDataObject::from(['enabled' => true]),
    $config
);

// 3. La tâche s'exécute automatiquement
// ./vendor/bin/directive tasks:process
```

---

## Voir aussi

- `GenericIndexBatchUniqueTask` - Tâche unique qui indexe chaque lot
- `AbstractRecurringTask` - Classe parente des tâches récurrentes
- `IndexerConfigInterface` - Configuration de l'indexeur
- `IndexableVO` - Value Object définissant le modèle et son cluster
- `UniqueTaskServiceInterface` - Service d'enregistrement des tâches uniques