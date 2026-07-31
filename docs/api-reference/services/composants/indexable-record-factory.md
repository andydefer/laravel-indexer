# IndexableRecordFactory - Référence Technique

## Description

Factory statique pour créer des instances de `IndexedDocumentRecord` à partir d'entités implémentant l'interface `Indexable`. Elle transforme une entité indexable en un enregistrement de document prêt à être persisté.

## Hiérarchie / Implémentations

```
IndexableRecordFactory (classe finale)
    └── Méthodes statiques uniquement
```

## Rôle principal

Cette factory est le point de passage obligé pour convertir des modèles Eloquent indexables en records de documents. Elle assure :

- La création cohérente des fingerprints au format `{morphClass}|{key}`
- L'encapsulation de la logique de conversion
- La séparation des responsabilités entre le domaine et la persistance

### Responsabilités

1. **Création du fingerprint** : Combine le `morphClass` et la clé primaire
2. **Construction du record** : Assemble fingerprint, cluster et données
3. **Encapsulation** : Cache les détails d'implémentation du fingerprint

## Détails

[Voir la classe IndexedDocumentRecord](https://github.com/andydefer/laravel-indexer/blob/main/src/Records/IndexedDocumentRecord.php)

[Voir l'interface Indexable](https://github.com/andydefer/laravel-indexer/blob/main/src/Contracts/Indexable.php)

## API / Méthodes publiques

### `convert(Indexable $entity, ClusterVO $cluster): IndexedDocumentRecord`

Convertit une entité indexable en un enregistrement de document.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$entity` | `Indexable` | Entité à convertir (doit implémenter `Indexable`) |
| `$cluster` | `ClusterVO` | Configuration cluster du document |

**Retourne :** `IndexedDocumentRecord` - Record de document prêt à être persisté

**Exemple :**
```php
$cluster = new ClusterVO(['status' => 'active', 'role' => 'admin']);
$record = IndexableRecordFactory::convert($user, $cluster);
// Record avec fingerprint 'App\Models\User|123'
```

---

## Cas d'utilisation

### Cas 1 : Indexation d'un modèle unique

Lors de l'indexation d'un modèle, on utilise la factory pour créer le record.

```php
use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;

class UserIndexer
{
    public function indexUser(User $user): void
    {
        $cluster = $user->getIndexableCluster();
        $record = IndexableRecordFactory::convert($user, $cluster);
        
        $this->indexer->index($record);
    }
}
```

### Cas 2 : Indexation par lots

Pour l'indexation en masse, on convertit chaque modèle en record.

```php
$records = new IndexableRecordCollection();

foreach ($models as $model) {
    $cluster = $model->getIndexableCluster();
    $record = IndexableRecordFactory::convert($model, $cluster);
    $records->add($record);
}

$this->indexer->indexMany($records);
```

### Cas 3 : Dans un service d'indexation générique

Le `GenericIndexerService` utilise la factory pour convertir les modèles indexables.

```php
// Extrait de GenericIndexerService::index()
public function index(Model&Indexable $model): void
{
    if (!$model->shouldBeIndexed()) {
        return;
    }

    $cluster = $model->getIndexableCluster();
    $record = IndexableRecordFactory::convert($model, $cluster);
    
    $fingerprint = IndexableFingerprintVO::fromParts(
        $model->getMorphClass(),
        (string) $model->getKey()
    );
    
    if ($this->documentRepository->existsByFingerPrint($fingerprint)) {
        $this->indexer->refresh($record);
    } else {
        $this->indexer->index($record);
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| `$entity` n'implémente pas `Indexable` | `TypeError` | `... must implement Indexable` |
| `$entity->getKey()` retourne `null` | Dépend de l'implémentation | - |

---

## Intégration

Cette factory est utilisée par :

- **`GenericIndexerService`** : Pour convertir les modèles en records
- **`GenericIndexBatchUniqueTask`** : Pour la conversion dans les tâches par lots
- **`IndexableRecordFactory`** : Utilisée directement dans les services d'indexation

### Flux de données

```
Model&Indexable
    │
    ├── getMorphClass() → string (ex: 'App\Models\User')
    ├── getKey() → int|string (ex: 123)
    ├── getIndexableData() → StrictAssociative
    └── getIndexableCluster() → ClusterVO
            │
            └── IndexableRecordFactory::convert()
                    │
                    └── IndexedDocumentRecord
                            ├── fingerprint: 'App\Models\User|123'
                            ├── cluster: ClusterVO
                            └── data: StrictAssociative
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `convert()` | O(1) | Création d'objets, pas d'accès base de données |

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

use AndyDefer\LaravelIndexer\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class User implements Indexable
{
    private int $id;
    private string $name;
    private string $email;
    private array $metadata;

    public function getMorphClass(): string
    {
        return 'App\Models\User';
    }

    public function getKey(): int
    {
        return $this->id;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'metadata' => $this->metadata,
        ]);
    }

    public function getIndexableCluster(): ClusterVO
    {
        return new ClusterVO([
            'type' => 'user',
            'status' => $this->metadata['status'] ?? 'inactive',
            'role' => $this->metadata['role'] ?? 'user',
        ]);
    }

    public function shouldBeIndexed(): bool
    {
        return ($this->metadata['status'] ?? '') === 'active';
    }
}

// Création et conversion
$user = new User(/* ... */);
$cluster = $user->getIndexableCluster();
$record = IndexableRecordFactory::convert($user, $cluster);

// $record est maintenant prêt pour l'indexation
echo $record->fingerprint->getValue(); // 'App\Models\User|123'
echo $record->cluster->get('role');   // 'admin'
echo $record->data->get('name');      // 'John Doe'
```

---

## Voir aussi

- `Indexable` - Interface que doivent implémenter les entités indexables
- `IndexedDocumentRecord` - Record de document indexé
- `ClusterVO` - Value Object de cluster
- `IndexableFingerprintVO` - Value Object de fingerprint
- `GenericIndexerService` - Service qui utilise cette factory