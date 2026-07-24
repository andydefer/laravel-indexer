# GenericIndexerService - Référence Technique

## Description

Service générique d'indexation pour les modèles Eloquent implémentant l'interface `Indexable`. Orchestre l'indexation, la suppression, le rafraîchissement et le comptage de documents indexés avec support du batch processing et de la limitation.

## Hiérarchie

```
GenericIndexerInterface
    └── GenericIndexerService
```

## Rôle principal

Fournit une interface unifiée pour l'indexation de n'importe quel modèle Eloquent qui implémente `Indexable`. Gère le chunking automatique des données, la construction des clusters, les opérations CRUD sur l'index, avec un contrôle fin via le batch size et la limite.

## API

### `__construct(IndexerInterface $indexer, IndexedDocumentRepositoryInterface $documentRepository, IndexerConfigInterface $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexer` | `IndexerInterface` | Service principal d'indexation |
| `$documentRepository` | `IndexedDocumentRepositoryInterface` | Repository des documents indexés |
| `$config` | `IndexerConfigInterface` | Configuration du package |

**Exemple :**
```php
$service = new GenericIndexerService(
    $indexer,
    $documentRepository,
    $config
);
```

### `setBatchSize(int $batchSize): self`

Définit la taille des lots pour le chunking.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$batchSize` | `int` | Nouvelle taille de lot |

**Retourne :** `self` - Instance courante pour chaînage

**Exemple :**
```php
$genericIndexer->setBatchSize(100)->indexAll($indexableVO);
```

### `setLimit(?int $limit): self`

Définit le nombre maximum d'éléments à indexer.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$limit` | `?int` | Nombre maximum d'éléments (null = illimité) |

**Retourne :** `self` - Instance courante pour chaînage

**Exemple :**
```php
$genericIndexer->setLimit(50)->indexAll($indexableVO);
```

### `index(IndexableVO $indexableVO, int $id): void`

Indexe un document spécifique par son ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle à indexer |
| `$id` | `int` | Identifiant du modèle |

**Retourne :** `void`

**Exceptions :** `ModelNotFoundException` - Si le modèle avec l'ID n'existe pas

**Exemple :**
```php
$cluster = new ClusterVO('type:doctor|specialty:cardiology');
$indexableVO = new IndexableVO(Doctor::class, $cluster);
$genericIndexer->index($indexableVO, 42);
```

### `indexAll(IndexableVO $indexableVO): void`

Indexe tous les modèles éligibles par lots.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle à indexer |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->setBatchSize(100)->setLimit(500)->indexAll($indexableVO);
```

### `reindexAll(IndexableVO $indexableVO): void`

Supprime puis réindexe tous les modèles éligibles.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle à réindexer |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->reindexAll($indexableVO);
```

### `delete(IndexableVO $indexableVO, int $id): void`

Supprime un document spécifique de l'index.

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

Supprime tous les documents d'un type de l'index.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle |

**Retourne :** `void`

**Exemple :**
```php
$genericIndexer->deleteAll($indexableVO);
```

### `refresh(IndexableVO $indexableVO, int $id): void`

Rafraîchit un document existant dans l'index (supprime puis réindexe).

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

Retourne le nombre de documents indexés pour un type de modèle.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexableVO` | `IndexableVO` | Configuration du modèle |

**Retourne :** `int` - Nombre de documents indexés

**Exemple :**
```php
$count = $genericIndexer->countIndexed($indexableVO);
```

### `exists(IndexableVO $indexableVO, int $id): bool`

Vérifie si un document est indexé.

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

### Cas 1 : Indexation avec batch et limite

```php
$cluster = new ClusterVO('type:doctor|status:active');
$indexableVO = new IndexableVO(Doctor::class, $cluster);

$genericIndexer
    ->setBatchSize(50)
    ->setLimit(1000)
    ->indexAll($indexableVO);
```

### Cas 2 : Réindexation complète en lots

```php
$cluster = new ClusterVO('type:user|role:doctor');
$indexableVO = new IndexableVO(User::class, $cluster);

$genericIndexer->setBatchSize(200)->reindexAll($indexableVO);
```

### Cas 3 : Indexation avec limite uniquement

```php
$cluster = new ClusterVO('type:product|status:published');
$indexableVO = new IndexableVO(Product::class, $cluster);

$genericIndexer->setLimit(100)->indexAll($indexableVO);
```

### Cas 4 : Mise à jour d'un document spécifique

```php
$doctor->specialty = 'Neurology';
$doctor->save();

$genericIndexer->refresh($indexableVO, $doctor->id);
```

### Cas 5 : Vérification et indexation conditionnelle

```php
if (!$genericIndexer->exists($indexableVO, $userId)) {
    $genericIndexer->index($indexableVO, $userId);
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
- **`IndexerConfigInterface`** - Configuration (batch size par défaut)
- **`IndexableVO`** - Configuration du modèle et du cluster
- **`ClusterVO`** - Définition des tags de regroupement

## Performance

- **Batch processing** : Traitement par lots via `chunk()` pour éviter les problèmes de mémoire
- **Batch size configurable** : Ajustable via `setBatchSize()`
- **Limitation** : Contrôle du nombre d'éléments via `setLimit()`
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

class DoctorIndexer
{
    public function __construct(
        private readonly GenericIndexerInterface $genericIndexer,
    ) {}

    public function indexDoctor(int $doctorId): void
    {
        $cluster = new ClusterVO('type:doctor|role:specialist|status:active');
        $indexableVO = new IndexableVO(Doctor::class, $cluster);

        $this->genericIndexer->index($indexableVO, $doctorId);
    }

    public function reindexActiveDoctors(): void
    {
        $cluster = new ClusterVO('type:doctor|status:active');
        $indexableVO = new IndexableVO(Doctor::class, $cluster);

        $this->genericIndexer
            ->setBatchSize(50)
            ->setLimit(10000)
            ->reindexAll($indexableVO);
    }

    public function getIndexedDoctorCount(): int
    {
        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(Doctor::class, $cluster);

        return $this->genericIndexer->countIndexed($indexableVO);
    }

    public function cleanupDoctorIndex(): void
    {
        $cluster = new ClusterVO('type:doctor');
        $indexableVO = new IndexableVO(Doctor::class, $cluster);

        $this->genericIndexer->deleteAll($indexableVO);
    }
}
```

## Voir aussi

- `Indexable` - Interface que les modèles doivent implémenter
- `IndexableVO` - Value Object de configuration
- `ClusterVO` - Value Object pour les tags de regroupement
- `IndexerInterface` - Service d'indexation principal
- `IndexerConfigInterface` - Configuration du package