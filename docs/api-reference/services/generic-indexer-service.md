# GenericIndexerService - Référence Technique

## Description

Service générique d'indexation pour les modèles Eloquent implémentant l'interface `Indexable`. Orchestre l'indexation, la suppression, le rafraîchissement et le comptage de documents indexés.

## Hiérarchie

```
GenericIndexerInterface
    └── GenericIndexerService
```

## Rôle principal

Fournit une interface unifiée pour l'indexation de n'importe quel modèle Eloquent qui implémente `Indexable`. Gère le chunking automatique des données, la construction des clusters et les opérations CRUD sur l'index.

## API

### `__construct(IndexerInterface $indexer, IndexedDocumentRepositoryInterface $documentRepository, int $batchSize = 50)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexer` | `IndexerInterface` | Service principal d'indexation |
| `$documentRepository` | `IndexedDocumentRepositoryInterface` | Repository des documents indexés |
| `$batchSize` | `int` | Nombre d'éléments par lot (défaut: 50) |

### `setBatchSize(int $batchSize): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$batchSize` | `int` | Nouvelle taille de lot |

**Retourne :** `self` - Instance courante pour chaînage

**Exemple :**
```php
$genericIndexer->setBatchSize(100)->indexAll($indexableVO);
```

### `index(IndexableVO $indexableVO, int $id): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle à indexer |
| `$id` | `int` | Identifiant du modèle |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle avec l'ID n'existe pas

**Exemple :**
```php
$cluster = new ClusterVO('type:doctor|specialty:cardiology');
$indexableVO = new IndexableVO(TestDoctor::class, $cluster);
$genericIndexer->index($indexableVO, 42);
```

### `indexAll(IndexableVO $indexableVO): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle à indexer |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->indexAll($indexableVO);
```

### `reindexAll(IndexableVO $indexableVO): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle à réindexer |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->reindexAll($indexableVO);
```

### `delete(IndexableVO $indexableVO, int $id): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle |
| `$id` | `int` | Identifiant du modèle à supprimer |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle avec l'ID n'existe pas

**Exemple :**
```php
$genericIndexer->delete($indexableVO, 42);
```

### `deleteAll(IndexableVO $indexableVO): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->deleteAll($indexableVO);
```

### `refresh(IndexableVO $indexableVO, int $id): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle |
| `$id` | `int` | Identifiant du modèle à rafraîchir |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle avec l'ID n'existe pas

**Exemple :**
```php
$genericIndexer->refresh($indexableVO, 42);
```

### `countIndexed(IndexableVO $indexableVO): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle |

**Retourne :** `int` - Nombre de documents indexés

**Exemple :**
```php
$count = $genericIndexer->countIndexed($indexableVO);
```

### `exists(IndexableVO $indexableVO, int $id): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle |
| `$id` | `int` | Identifiant du modèle |

**Retourne :** `bool` - True si le document est indexé

**Exemple :**
```php
if ($genericIndexer->exists($indexableVO, 42)) {
    // Le document existe dans l'index
}
```

## Cas d'utilisation

### Cas 1 : Indexer un modèle spécifique avec cluster multiple

```php
$cluster = new ClusterVO('type:doctor|specialty:cardiology|status:active');
$indexableVO = new IndexableVO(Doctor::class, $cluster);

$genericIndexer->index($indexableVO, 42);
```

### Cas 2 : Indexer tous les modèles actifs par lots

```php
$cluster = new ClusterVO('type:user|role:doctor');
$indexableVO = new IndexableVO(User::class, $cluster);

$genericIndexer->setBatchSize(100)->indexAll($indexableVO);
```

### Cas 3 : Reconstruire l'index complet

```php
$cluster = new ClusterVO('type:hospital|status:active');
$indexableVO = new IndexableVO(Hospital::class, $cluster);

// Supprime puis réindexe tous les hôpitaux
$genericIndexer->reindexAll($indexableVO);
```

### Cas 4 : Mettre à jour un document après modification

```php
// Après avoir modifié le docteur
$doctor->specialty = 'Neurology';
$doctor->save();

// Rafraîchir l'index
$genericIndexer->refresh($indexableVO, $doctor->id);
```

### Cas 5 : Vérifier l'existence avant indexation

```php
if (!$genericIndexer->exists($indexableVO, $doctorId)) {
    $genericIndexer->index($indexableVO, $doctorId);
}
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle introuvable | `ModelNotFoundException` | `Model with ID {id} not found` |
| Modèle n'implémente pas Indexable | `InvalidArgumentException` | `Class {class} must implement Indexable` (levée par IndexableVO) |

## Intégration

Le service s'intègre avec :

- **`IndexerInterface`** - Service d'indexation principal
- **`IndexedDocumentRepositoryInterface`** - Persistance des documents
- **`IndexableVO`** - Configuration du modèle et du cluster
- **`ClusterVO`** - Définition des tags de regroupement

## Performance

- **Batch processing** : Traitement par lots via `chunk()` pour éviter les problèmes de mémoire
- **Batch size configurable** : Ajustable via `setBatchSize()`
- **Skip automatique** : Les modèles non éligibles (`shouldBeIndexed() = false`) sont ignorés

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Contracts\GenericIndexerInterface;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;

class UserIndexer
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer,
    ) {}

    public function indexUser(int $userId): void
    {
        $cluster = new ClusterVO('type:user|role:doctor|status:active');
        $indexableVO = new IndexableVO(User::class, $cluster);

        $this->genericIndexer->index($indexableVO, $userId);
    }

    public function reindexAllDoctors(): void
    {
        $cluster = new ClusterVO('type:user|role:doctor');
        $indexableVO = new IndexableVO(User::class, $cluster);

        $this->genericIndexer->setBatchSize(50)->reindexAll($indexableVO);
    }

    public function getIndexedUserCount(): int
    {
        $cluster = new ClusterVO('type:user');
        $indexableVO = new IndexableVO(User::class, $cluster);

        return $this->genericIndexer->countIndexed($indexableVO);
    }
}
```

## Voir aussi

- `Indexable` - Interface que les modèles doivent implémenter
- `IndexableVO` - Value Object de configuration
- `ClusterVO` - Value Object pour les tags de regroupement
- `IndexerInterface` - Service d'indexation principal