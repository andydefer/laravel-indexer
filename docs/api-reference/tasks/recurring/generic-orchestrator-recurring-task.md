# GenericOrchestratorRecurringTask - Référence Technique

## Description

Tâche récurrente qui orchestre l'indexation de multiples classes de modèles. Récupère toutes les classes configurées et distribue des tâches par lots pour chaque groupe de modèles. Agit comme un coordinateur qui décompose les grandes opérations d'indexation en tâches uniques gérables.

## Hiérarchie / Implémentations

```
AbstractRecurringTask
    └── GenericOrchestratorRecurringTask
```

## Rôle principal

Cette tâche est le **coordinateur** du système d'indexation programmée. Elle :

- Récupère toutes les classes de modèles configurées
- Découpe les modèles en chunks selon la taille configurée
- Enregistre chaque chunk comme une `UniqueTask` distincte
- Assure la répartition de la charge d'indexation

### Responsabilités

1. **Coordination** : Orchestre l'indexation de tous les modèles configurés
2. **Découpage** : Fragmente les grands volumes en lots gérables
3. **Distribution** : Enregistre les tâches uniques pour traitement asynchrone
4. **Logging** : Suivi de l'avancement et des statistiques

## Détails

[Voir la classe AbstractRecurringTask](https://github.com/andydefer/task/blob/main/src/Abstract/AbstractRecurringTask.php)

[Voir la classe GenericIndexBatchUniqueTask](https://github.com/andydefer/laravel-indexer/blob/main/src/Tasks/UniqueTasks/GenericIndexBatchUniqueTask.php)

## API / Méthodes publiques

### `process(): void`

Méthode principale de la tâche. Orchestre l'ensemble du processus d'indexation.

**Retourne :** `void`

**Comportement :**
1. Récupère les classes de modèles configurées
2. Pour chaque classe, récupère les IDs des modèles à indexer
3. Découpe les IDs en chunks selon `batchSize`
4. Pour chaque chunk, enregistre un `GenericIndexBatchUniqueTask`

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
GenericOrchestratorRecurringTask::process()
    │
    ├── Récupère les classes de modèles configurées
    │   └── indexer.model_indexables (ex: [User::class, Product::class])
    │
    ├── Pour chaque classe de modèle
    │   │
    │   ├── getModelChunks()
    │   │   ├── chunk() par batchSize
    │   │   ├── shouldBeIndexed() → filtre les modèles actifs
    │   │   └── Retourne les chunks d'IDs
    │   │
    │   └── Pour chaque chunk
    │       │
    │       ├── buildIndexableCollection()
    │       │   └── Crée IndexableVOCollection
    │       │
    │       ├── Crée le payload
    │       │   └── ['items' => IndexableVOCollection]
    │       │
    │       ├── Configure la UniqueTask
    │       │   ├── scheduled_at: maintenant
    │       │   ├── max_attempts: 3
    │       │   ├── grace_period: 3600s
    │       │   └── description: "Batch task for indexing {modelClass}"
    │       │
    │       └── Enregistre GenericIndexBatchUniqueTask
    │
    └── Affiche les statistiques
```

---

## Cas d'utilisation

### Cas 1 : Indexation programmée quotidienne

```php
// config/tasks.php
return [
    'recurring' => [
        GenericOrchestratorRecurringTask::class => [
            'schedule' => '0 2 * * *', // Tous les jours à 2h
        ],
    ],
];
```

### Cas 2 : Indexation après import de données

```php
class DataImportService
{
    public function import(array $data): void
    {
        // Importer les données
        foreach ($data as $row) {
            Product::create($row);
        }
        
        // Déclencher l'orchestrateur pour indexer les nouveaux produits
        $task = app(GenericOrchestratorRecurringTask::class);
        $task->run();
    }
}
```

### Cas 3 : Indexation manuelle depuis une commande

```php
class IndexCommand extends Command
{
    public function handle(): void
    {
        $this->info('Démarrage de l\'orchestrateur...');
        
        $task = app(GenericOrchestratorRecurringTask::class);
        $task->run();
        
        $this->info('Orchestrateur terminé.');
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Échec de l'enregistrement d'une tâche | `Throwable` | Propage l'exception |
| Configuration invalide | `InvalidArgumentException` | Variable selon la configuration |

---

## Intégration

Cette tâche s'intègre avec :

- **`IndexerConfigInterface`** : Pour la configuration du batch size
- **`UniqueTaskServiceInterface`** : Pour l'enregistrement des tâches uniques
- **`GenericIndexBatchUniqueTask`** : La tâche unique exécutée pour chaque chunk
- **`IndexableVOCollection`** : Collection des objets valeur indexables

### Flux de données

```
GenericOrchestratorRecurringTask
    │
    ├── Config → batchSize
    │
    ├── ModelClass → chunks d'IDs
    │
    └── UniqueTaskService
        │
        └── GenericIndexBatchUniqueTask
            │
            └── IndexerService
                │
                └── IndexWriter
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `getModelChunks()` | O(n) | n = nombre de modèles |
| `buildIndexableCollection()` | O(n) | n = taille du chunk |
| Enregistrement des tâches | O(n / batchSize) | n = nombre total de modèles |

**Optimisations :**

- Les modèles exclus par `shouldBeIndexed()` ne génèrent pas de tâches
- Les chunks évitent la surcharge mémoire
- Les tâches uniques permettent un traitement parallèle

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

// Configuration dans config/indexer.php
return [
    'model_indexables' => [
        App\Models\User::class,
        App\Models\Product::class,
        App\Models\Order::class,
    ],
    'batch_size' => 50,
];

// Configuration dans config/tasks.php
return [
    'recurring' => [
        GenericOrchestratorRecurringTask::class => [
            'schedule' => '0 2 * * *', // Tous les jours à 2h
            'enabled' => true,
        ],
    ],
];

// Exécution manuelle
$task = app(GenericOrchestratorRecurringTask::class);
$task->run();

// Résultat attendu :
// Starting generic orchestrator: finding models to index...
// Processing App\Models\User...
// Dispatched 100 App\Models\User in 2 batches
// Processing App\Models\Product...
// Dispatched 50 App\Models\Product in 1 batches
// Processing App\Models\Order...
// Dispatched 75 App\Models\Order in 2 batches
// Orchestrator completed: 225 items dispatched in 5 batch tasks
```

---

## Voir aussi

- `AbstractRecurringTask` - Classe parente des tâches récurrentes
- `GenericIndexBatchUniqueTask` - Tâche unique exécutée par lot
- `IndexerConfigInterface` - Configuration de l'indexation
- `UniqueTaskServiceInterface` - Service d'enregistrement des tâches
- `IndexableVOCollection` - Collection des objets valeur indexables