# GenericIndexModelsDirective - Référence Technique

## Description

Directive CLI permettant d'indexer des modèles Eloquent configurés dans le fichier de configuration du package Laravel Indexer.

## Hiérarchie

```
AbstractDirective
    └── GenericIndexModelsDirective
```

## Rôle principal

Expose une interface en ligne de commande pour indexer, réindexer, compter ou supprimer des modèles de l'index. Supporte les arguments de batch et de limite pour un contrôle fin des opérations.

## Signature

```
index:models {batch=50} {limit=?} {models*} {--reindex} {--count} {--delete}
```

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature de la directive.

**Retourne :** `string` - Signature au format Laravel

**Exemple :**
```php
$directive = new GenericIndexModelsDirective($kernel, 'index:models batch=100 limit=10 [App.Models.User,App.Models.Hospital]');
echo $directive->getSignature();
// index:models {batch=50} {limit=?} {models*} {--reindex} {--count} {--delete}
```

### `getDescription(): string`

Retourne la description de la directive.

**Retourne :** `string` - Description en français

**Exemple :**
```php
echo $directive->getDescription();
// Index models from config (App.Models.User, App.Models.Hospital, etc.)
```

### `getAliases(): StringTypedCollection`

Retourne les alias de la directive.

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```php
$aliases = $directive->getAliases();
// ['idx:models', 'indexer:models']
```

### `execute(): ExitCode`

Exécute la directive selon les arguments et flags passés.

**Retourne :** `ExitCode` - Code de sortie (SUCCESS, INVALID_ARGUMENT, FAILURE)

**Exceptions :** `Throwable` - Erreurs pendant l'exécution

**Exemple :**
```bash
# Indexer tous les modèles configurés
./bin/directive index:models [App.Models.User,App.Models.Hospital]

# Indexer avec batch=10 et limit=5
./bin/directive index:models 10 5 [App.Models.User]

# Compter les documents indexés
./bin/directive index:models [App.Models.User] --count

# Supprimer tout l'index des modèles
./bin/directive index:models [App.Models.User] --delete

# Réindexer avec batch et limit
./bin/directive index:models 20 10 [App.Models.User] --reindex
```

## Cas d'utilisation

### Cas 1 : Indexer tous les modèles avec batch personnalisé

```bash
./bin/directive index:models 100 [App.Models.User,App.Models.Hospital,App.Models.Specialty]
```

### Cas 2 : Indexer avec limite

```bash
./bin/directive index:models _ 20 [App.Models.User]
```

### Cas 3 : Compter les documents indexés

```bash
./bin/directive index:models [App.Models.User] --count
```

### Cas 4 : Réindexer avec batch et limit

```bash
./bin/directive index:models 10 5 [App.Models.User] --reindex
```

### Cas 5 : Supprimer tout l'index d'un modèle

```bash
./bin/directive index:models [App.Models.User] --delete
```

## Gestion des erreurs

| Situation | Code de sortie | Message |
|-----------|----------------|---------|
| Aucun modèle spécifié | `ExitCode::INVALID_ARGUMENT` | `No models specified.` |
| Classe de modèle inexistante | `ExitCode::INVALID_ARGUMENT` | `Class '{class}' does not exist` |
| Modèle non configuré | `ExitCode::INVALID_ARGUMENT` | `Model '{class}' is not configured in indexer.model_indexables` |
| Erreur d'exécution | `ExitCode::FAILURE` | Message de l'exception |

## Intégration

Cette directive s'intègre avec :

- **`GenericIndexerInterface`** - Service d'indexation générique
- **`IndexerConfigInterface`** - Configuration du package
- **`IndexableVO`** - Value Object de configuration d'indexation
- **`ClusterVO`** - Value Object pour les tags de regroupement

## Performance

- Utilise le chunking pour éviter les problèmes de mémoire
- Supporte le batch size pour optimiser les insertions
- La limite permet de contrôler le nombre d'éléments indexés
- Les flags `--count` et `--delete` sont O(1) ou O(n) selon l'opération

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10+ | ✅ Complet |

## Exemple complet

```bash
#!/bin/bash

# Indexer tous les modèles avec configuration par défaut
./bin/directive index:models [App.Models.User,App.Models.Hospital,App.Models.Specialty]

# Indexer avec batch=25 et limit=100
./bin/directive index:models 25 100 [App.Models.User]

# Compter les utilisateurs indexés
./bin/directive index:models [App.Models.User] --count

# Réindexer les hôpitaux en batch de 50
./bin/directive index:models 50 [App.Models.Hospital] --reindex

# Supprimer toutes les spécialités de l'index
./bin/directive index:models [App.Models.Specialty] --delete
```

## Voir aussi

- `GenericIndexerService` - Service d'indexation générique
- `IndexerConfigInterface` - Interface de configuration
- `IndexableVO` - Value Object d'indexation
- `ClusterVO` - Value Object de cluster