# IndexDeleter - Référence Technique

## Description

Service de suppression des documents indexés et de leurs tokens associés. Fournit des méthodes pour la suppression unitaire, par lots et le nettoyage complet de l'index.

## Hiérarchie / Implémentations

```
IndexDeleter (classe finale)
    ├── Dépend de IndexedDocumentRepository
    └── Dépend de IndexedTokenRepository
```

## Rôle principal

Ce service est le point d'entrée unique pour toutes les opérations de suppression dans le système d'indexation. Il orchestre :

- La suppression d'un document unique par son fingerprint
- La suppression multiple par collection de fingerprints
- Le nettoyage complet de l'index (documents et tokens)

### Responsabilités

1. **Suppression unitaire** : `delete()`
2. **Suppression par lots** : `deleteMany()`
3. **Nettoyage complet** : `clear()`

## Détails

[Voir la classe IndexedDocumentRepository](https://github.com/andydefer/laravel-indexer/blob/main/src/Repositories/IndexedDocumentRepository.php)

[Voir la classe IndexedTokenRepository](https://github.com/andydefer/laravel-indexer/blob/main/src/Repositories/IndexedTokenRepository.php)

## API / Méthodes publiques

### `delete(IndexableFingerprintVO $fingerprint): void`

Supprime un document unique par son fingerprint. Les tokens associés sont automatiquement supprimés via la cascade de la base de données.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint du document à supprimer |

**Retourne :** `void`

**Exemple :**
```php
$fingerprint = new IndexableFingerprintVO('App\Models\User|123');
$deleter->delete($fingerprint);
// Le document et tous ses tokens sont supprimés
```

---

### `deleteMany(IndexableFingerPrintVOCollection $fingerprints): void`

Supprime plusieurs documents par leurs fingerprints.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprints` | `IndexableFingerPrintVOCollection` | Collection des fingerprints à supprimer |

**Retourne :** `void`

**Exemple :**
```php
$fingerprints = new IndexableFingerPrintVOCollection();
$fingerprints->add(new IndexableFingerprintVO('App\Models\User|1'));
$fingerprints->add(new IndexableFingerprintVO('App\Models\User|2'));
$fingerprints->add(new IndexableFingerprintVO('App\Models\Product|5'));

$deleter->deleteMany($fingerprints);
// Tous les documents et leurs tokens sont supprimés
```

---

### `clear(): void`

Supprime tous les documents et tous les tokens de l'index. Opération destructive qui vide complètement l'index.

**Retourne :** `void`

**⚠️ Attention :** Cette opération est irréversible. Elle supprime toutes les données indexées.

**Exemple :**
```php
$deleter->clear();
// L'index est entièrement vidé
```

---

## Cas d'utilisation

### Cas 1 : Suppression d'un modèle après suppression en base

Lorsqu'un modèle est supprimé de la base de données, on le supprime de l'index.

```php
class UserObserver
{
    public function __construct(
        private readonly IndexDeleter $deleter
    ) {}

    public function deleted(User $user): void
    {
        $fingerprint = IndexableFingerprintVO::fromParts(
            $user->getMorphClass(),
            (string) $user->getKey()
        );
        
        $this->deleter->delete($fingerprint);
    }
}
```

### Cas 2 : Suppression par lots après nettoyage de données

Lors d'une opération de nettoyage, on supprime plusieurs documents en une seule fois.

```php
class DataCleanupService
{
    public function __construct(
        private readonly IndexDeleter $deleter
    ) {}

    public function cleanupInactiveUsers(array $userIds): void
    {
        $fingerprints = new IndexableFingerPrintVOCollection();
        
        foreach ($userIds as $id) {
            $fingerprints->add(
                IndexableFingerprintVO::fromParts('App\Models\User', (string) $id)
            );
        }
        
        $this->deleter->deleteMany($fingerprints);
    }
}
```

### Cas 3 : Réinitialisation complète de l'index

Lors d'une réindexation complète, on vide l'index avant de le reconstruire.

```php
class ReindexService
{
    public function __construct(
        private readonly IndexDeleter $deleter,
        private readonly GenericIndexerInterface $indexer
    ) {}

    public function reindexAll(): void
    {
        // 1. Vider l'index
        $this->deleter->clear();
        
        // 2. Réindexer tous les modèles configurés
        $models = config('indexer.model_indexables');
        foreach ($models as $modelClass) {
            $this->indexer->indexAll($modelClass);
        }
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Document non trouvé | - | Aucune exception, retour silencieux |
| Erreur de suppression | `Throwable` | Dépend de la couche base de données |

---

## Intégration

Ce service est utilisé par :

- **`IndexerService`** : Pour les opérations de suppression
- **`GenericIndexerService`** : Pour `delete()`, `deleteAll()`, `refresh()`
- **`GenericIndexBatchUniqueTask`** : Pour la suppression avant réindexation

### Flux de données

```
IndexerService
    │
    ├── delete() → IndexDeleter::delete()
    ├── deleteMany() → IndexDeleter::deleteMany()
    └── clear() → IndexDeleter::clear()
            │
            ├── tokenRepository->delete()
            └── documentRepository->delete()
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `delete()` | O(1) | Suppression par fingerprint |
| `deleteMany()` | O(n) | n = nombre de fingerprints |
| `clear()` | O(n) | Supprime tous les enregistrements |

**Considérations :**

- Les tokens sont supprimés en cascade (via les contraintes de clé étrangère)
- `deleteMany()` effectue une suppression individuelle par document
- `clear()` supprime d'abord les tokens puis les documents

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

use AndyDefer\LaravelIndexer\Services\Composants\IndexDeleter;
use AndyDefer\LaravelIndexer\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;

$deleter = new IndexDeleter(
    $documentRepository,
    $tokenRepository
);

// 1. Suppression unitaire
$fingerprint = new IndexableFingerprintVO('App\Models\User|123');
$deleter->delete($fingerprint);

// 2. Suppression par lots
$fingerprints = new IndexableFingerPrintVOCollection();
$fingerprints->add(new IndexableFingerprintVO('App\Models\User|1'));
$fingerprints->add(new IndexableFingerprintVO('App\Models\User|2'));
$fingerprints->add(new IndexableFingerprintVO('App\Models\Product|5'));
$deleter->deleteMany($fingerprints);

// 3. Nettoyage complet
$deleter->clear();
```

---

## Voir aussi

- `IndexedDocumentRepository` - Repository des documents
- `IndexedTokenRepository` - Repository des tokens
- `IndexableFingerprintVO` - Value Object de fingerprint
- `IndexerService` - Service principal qui utilise ce composant