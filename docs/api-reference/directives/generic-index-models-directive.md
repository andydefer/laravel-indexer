# GenericIndexModelsDirective - Référence Technique

## Description

Directive CLI permettant d'indexer, réindexer, compter et supprimer des modèles Eloquent depuis la ligne de commande. Elle fait partie du système `laravel-directive` et offre une interface alternative aux commandes Artisan.

## Hiérarchie / Implémentations

```
AbstractDirective
    └── GenericIndexModelsDirective
```

## Rôle principal

Cette directive sert d'interface utilisateur pour les opérations d'indexation en masse. Elle permet de :

- Indexer de nouveaux modèles (sans supprimer les existants)
- Réindexer tous les modèles (suppression puis réindexation)
- Compter les documents indexés par classe de modèle
- Supprimer tous les documents indexés d'une classe

Elle est conçue pour être utilisée dans des environnements de production via des scripts ou des tâches planifiées.

## Détails

[Voir la classe AbstractDirective](https://github.com/andydefer/laravel-directive/blob/main/src/AbstractDirective.php)

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature de la commande avec ses paramètres et options.

**Retourne :** `string` - Signature de la commande

**Paramètres :**
- `batch` (int, défaut: 50) - Taille des lots pour le chunking
- `limit` (int, optionnel) - Nombre maximum d'éléments à indexer
- `models*` (variadique) - Liste des modèles à indexer (notation pointée)
- `--reindex` (flag) - Supprime puis réindexe tous les modèles
- `--count` (flag) - Compte les documents indexés
- `--delete` (flag) - Supprime tous les documents indexés

**Exemple :**
```bash
bin/directive index:models 50 100 [App.Models.User,App.Models.Product] --reindex
```

---

### `getDescription(): string`

Retourne la description de la commande affichée dans l'aide.

**Retourne :** `string` - Description de la commande

---

### `getAliases(): StringTypedCollection`

Retourne les alias de la commande.

**Retourne :** `StringTypedCollection` - Collection des alias

**Alias disponibles :**
- `idx:models`
- `indexer:models`

**Exemple :**
```bash
bin/directive idx:models [App.Models.User]
```

---

### `shouldBootLaravel(): bool`

Indique si l'application Laravel doit être bootée avant l'exécution.

**Retourne :** `bool` - Toujours `true` car cette directive nécessite l'accès aux services Laravel

---

### `execute(): ExitCode`

Point d'entrée principal de la directive. Analyse les arguments, résout les classes de modèles et délègue à la méthode appropriée.

**Retourne :** `ExitCode` - Code de sortie (`SUCCESS`, `INVALID_ARGUMENT` ou `FAILURE`)

**Exemple :**
```bash
bin/directive index:models [App.Models.User,App.Models.Hospital] --count
```

---

## Cas d'utilisation

### Cas 1 : Indexation initiale

Lors du déploiement initial, on indexe tous les modèles configurés.

```bash
bin/directive index:models [App.Models.User,App.Models.Product,App.Models.Order]
```

### Cas 2 : Réindexation après modification de schéma

Après une modification du schéma de données ou des règles d'indexation, on réindexe tout.

```bash
bin/directive index:models [App.Models.User,App.Models.Product] --reindex
```

### Cas 3 : Comptage des documents indexés

Pour vérifier l'état de l'index avant une opération.

```bash
bin/directive index:models [App.Models.User,App.Models.Product] --count
```

### Cas 4 : Nettoyage partiel

Pour supprimer les données indexées d'un type spécifique.

```bash
bin/directive index:models [App.Models.TempData] --delete
```

### Cas 5 : Indexation avec limites

Pour tester ou indexer un sous-ensemble de données.

```bash
# Indexer seulement 100 utilisateurs avec des lots de 10
bin/directive index:models 10 100 [App.Models.User]
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucun modèle spécifié | - | `❌ No models specified.` |
| Classe de modèle inexistante | - | `❌ Class '{class}' does not exist` |
| Modèle non configuré | - | `❌ Model '{class}' is not configured in indexer.model_indexables` |
| Aucun modèle valide trouvé | - | `❌ No valid models found in config.` |
| Erreur inattendue | `Throwable` | `❌ {message}` |

---

## Intégration

Cette directive s'intègre avec :

- **`GenericIndexerInterface`** : Service d'indexation générique
- **`IndexerConfigInterface`** : Configuration du système d'indexation
- **`AbstractDirective`** : Classe parente du système `laravel-directive`

### Flux de données

```
GenericIndexModelsDirective
    │
    ├── Résout les classes de modèles
    │   └── Vérifie existence + configuration
    │
    ├── Mode --count
    │   └── genericIndexer->countIndexed()
    │
    ├── Mode --delete
    │   └── genericIndexer->deleteAll()
    │
    ├── Mode --reindex
    │   └── genericIndexer->reindexAll()
    │       ├── deleteAll()
    │       └── indexAll()
    │
    └── Mode index (défaut)
        └── genericIndexer->indexAll()
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `index:models` | O(n * m) | n = nombre de modèles, m = nombre total d'entités |
| `--reindex` | O(2 * n * m) | Supprime puis réindexe |
| `--count` | O(1) | Une requête COUNT par classe |
| `--delete` | O(n) | Suppression par namespace |

### Optimisations

- **Batch size** : Contrôle le nombre d'éléments traités par lot
- **Limit** : Permet de tester sur un sous-ensemble
- **Indexation différée** : Les lots sont traités séquentiellement

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| laravel-directive 2.x+ | ✅ Complet |

---

## Exemple complet

```bash
#!/bin/bash

# 1. Vérifier l'état actuel
bin/directive index:models [App.Models.User,App.Models.Product] --count

# 2. Réindexer tous les produits avec un batch de 100
bin/directive index:models 100 [App.Models.Product] --reindex

# 3. Indexer uniquement les 50 premiers utilisateurs
bin/directive index:models 50 50 [App.Models.User]

# 4. Vérifier le résultat
bin/directive index:models [App.Models.User,App.Models.Product] --count

# 5. Nettoyer les données temporaires
bin/directive index:models [App.Models.TempData] --delete
```

### Intégration dans une tâche CRON

```bash
# Toutes les nuits à 2h00, réindexer tous les modèles
0 2 * * * cd /var/www/project && bin/directive index:models [App.Models.User,App.Models.Product,App.Models.Order] --reindex
```

---

## Voir aussi

- `GenericIndexerInterface` - Service d'indexation générique
- `IndexerConfigInterface` - Configuration du système d'indexation
- `AbstractDirective` - Classe parente des directives CLI
- `GenericIndexBatchUniqueTask` - Tâche d'indexation par lots