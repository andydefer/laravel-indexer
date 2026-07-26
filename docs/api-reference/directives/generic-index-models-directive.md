# GenericIndexModelsDirective - Référence Technique

## Description

Directive CLI pour indexer les modèles configurés avec des clusters dynamiques. Elle orchestre l'indexation des modèles Eloquent implémentant l'interface `Indexable`.

## Hiérarchie / Implémentations

```
AbstractDirective
    └── GenericIndexModelsDirective
```

**Interfaces :** `AbstractDirective` (via héritage)

**Lien source :** [GenericIndexModelsDirective.php](https://github.com/andydefer/laravel-indexer/blob/main/src/Directives/GenericIndexModelsDirective.php)

## Rôle principal

Permet d'indexer en masse des modèles via la ligne de commande. Contrairement à l'approche traditionnelle avec des clusters statiques, cette directive utilise les clusters dynamiques générés par chaque modèle via la méthode `getIndexableCluster()`.

---

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature de la commande CLI.

**Retourne :** `string` - La signature de la commande

**Exemple :**
```bash
bin/afya index:models [App.Models.User,App.Models.Hospital] --reindex
```

---

### `getDescription(): string`

Retourne la description de la commande.

**Retourne :** `string` - Description de la commande

**Exemple :**
```bash
bin/afya index:models --help
# Affiche : Index models from config (App.Models.User, App.Models.Hospital, etc.) with dynamic clusters
```

---

### `getAliases(): StringTypedCollection`

Retourne les alias de la commande.

**Retourne :** `StringTypedCollection` - Collection des alias

**Exemple :**
```bash
bin/afya idx:models [App.Models.User]
bin/afya indexer:models [App.Models.User]
```

---

### `execute(): ExitCode`

Point d'entrée de la directive. Parse les arguments et exécute l'action demandée.

**Retourne :** `ExitCode` - Code de sortie (`SUCCESS`, `FAILURE`, `INVALID_ARGUMENT`)

**Exceptions :** `Throwable` - Toute exception est capturée et transformée en message d'erreur

---

## Paramètres de la commande

| Paramètre | Type | Description |
|-----------|------|-------------|
| `batch` | `int` | Taille des lots pour le chunking (défaut: 50) |
| `limit` | `int|null` | Nombre maximum d'éléments à indexer (optionnel) |
| `models*` | `array` | Liste des modèles à indexer (notation pointée: `App.Models.User`) |
| `--reindex` | `bool` | Supprime puis réindexe tous les modèles |
| `--count` | `bool` | Compte les documents indexés |
| `--delete` | `bool` | Supprime tous les documents de l'index |

---

## Cas d'utilisation

### Cas 1 : Indexer tous les modèles configurés

```bash
bin/afya index:models [App.Models.User,App.Models.Hospital,App.Models.Specialty]
```

**Résultat :** Tous les modèles configurés sont indexés avec leurs clusters dynamiques respectifs.

---

### Cas 2 : Réindexer avec un batch et une limite

```bash
bin/afya index:models 20 100 [App.Models.User] --reindex
```

**Résultat :** Les utilisateurs sont réindexés par lots de 20, avec une limite de 100 éléments.

---

### Cas 3 : Compter les documents indexés

```bash
bin/afya index:models [App.Models.User] --count
```

**Résultat :** Affiche le nombre de documents indexés pour le modèle `User`.

**Exemple de sortie :**
```
📊 Indexed App\Models\User: 150
📈 Total indexed: 150
```

---

### Cas 4 : Supprimer tous les documents d'un modèle

```bash
bin/afya index:models [App.Models.User] --delete
```

**Résultat :** Tous les documents indexés pour le modèle `User` sont supprimés.

**Exemple de sortie :**
```
🗑️ All App\Models\User deleted from index
🗑️ Total models cleared: 1
```

---

## Gestion des erreurs

| Situation | Code | Message |
|-----------|------|---------|
| Aucun modèle spécifié | `INVALID_ARGUMENT` | `No models specified.` |
| Classe de modèle inexistante | `INVALID_ARGUMENT` | `Class 'Invalid\Model' does not exist` |
| Modèle non configuré | `INVALID_ARGUMENT` | `Model 'App\Models\Other' is not configured in indexer.model_indexables` |
| Erreur d'exécution | `FAILURE` | Message d'erreur de l'exception |

---

## Intégration

### Avec IndexerConfig

La directive utilise `IndexerConfigInterface` pour récupérer la liste des modèles indexables :

```php
$validClasses = $indexerConfig->getModelIndexables();
```

### Avec GenericIndexerService

La directive délègue toutes les opérations d'indexation à `GenericIndexerInterface` :

```php
$genericIndexer->indexAll($modelClass);
$genericIndexer->deleteAll($modelClass);
$genericIndexer->countIndexed($modelClass);
```

### Avec les clusters dynamiques

Les clusters sont récupérés dynamiquement par `GenericIndexerService` via la méthode `getIndexableCluster()` de chaque modèle. La directive n'a pas à gérer les clusters.

---

## Performance

- **Batch processing** : Les opérations d'indexation sont traitées par lots (batch) pour optimiser la mémoire
- **Limitation** : Possibilité de limiter le nombre d'éléments à traiter
- **Chunking** : Utilisation du chunking d'Eloquent pour éviter de charger tous les modèles en mémoire
- **Complexité** : O(n) où n est le nombre de modèles à indexer

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

```bash
# 1. Indexer tous les modèles configurés
bin/afya index:models [App.Models.User,App.Models.Hospital,App.Models.Specialty]

# 2. Compter les documents indexés
bin/afya index:models [App.Models.User] --count

# 3. Indexer avec batch et limite
bin/afya index:models 25 500 [App.Models.User,App.Models.Hospital]

# 4. Réindexer avec reindex
bin/afya index:models [App.Models.User] --reindex

# 5. Supprimer tout l'index d'un modèle
bin/afya index:models [App.Models.Hospital] --delete

# 6. Utiliser un alias
bin/afya idx:models [App.Models.User]

# 7. Voir l'aide
bin/afya index:models --help
```

---

## Voir aussi

- `GenericIndexerService` - Service d'indexation générique
- `Indexable` - Interface pour les modèles indexables
- `ClusterVO` - Value Object pour les clusters dynamiques
- `IndexerConfig` - Configuration des modèles indexables