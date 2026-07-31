# IndexableVO - Référence Technique

## Description

Value Object représentant une entité indexable. Contient le nom de la classe du modèle et son ID, avec des méthodes pour récupérer l'instance du modèle et valider que la classe implémente l'interface `Indexable`.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── IndexableVO
```

## Rôle principal

Ce Value Object est une référence légère à une entité indexable. Il :

- Stocke la classe du modèle et son ID
- Valide que la classe existe et implémente `Indexable`
- Fournit une récupération optimisée des instances
- Permet la manipulation en collection sans charger les modèles

### Utilisations principales

1. **Référence légère** : Stocke la référence sans charger le modèle
2. **Traitement par lots** : Utilisé dans `IndexableVOCollection`
3. **Optimisation des requêtes** : `getModelInstances()` exécute une requête par classe
4. **Tâches d'indexation** : Transport des références dans les tâches

## Détails

[Voir l'interface Indexable](https://github.com/andydefer/laravel-indexer/blob/main/src/Contracts/Indexable.php)

[Voir la classe IndexableVOCollection](https://github.com/andydefer/laravel-indexer/blob/main/src/Collections/IndexableVOCollection.php)

## API / Méthodes publiques

### `__construct(string $modelClass, int|string $id)`

Constructeur qui valide la classe et stocke les références.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Nom de classe qualifié du modèle |
| `$id` | `int|string` | ID de l'entité |

**Exceptions :** `InvalidArgumentException` si la classe est invalide

**Exemple :**
```php
$indexable = new IndexableVO('App\Models\User', 123);
```

---

### `getModelClass(): string`

Retourne le nom de la classe du modèle.

**Retourne :** `string` - Nom de classe qualifié

**Exemple :**
```php
$class = $indexable->getModelClass(); // 'App\Models\User'
```

---

### `getId(): int|string`

Retourne l'ID de l'entité.

**Retourne :** `int|string` - ID de l'entité

**Exemple :**
```php
$id = $indexable->getId(); // 123
```

---

### `getInstance(): Model&Indexable`

Récupère l'instance du modèle.

**Retourne :** `Model&Indexable` - Instance du modèle

**Exceptions :** `ModelNotFoundException` si le modèle n'est pas trouvé

**Exemple :**
```php
$user = $indexable->getInstance();
echo $user->name; // John Doe
```

---

### `getValue(): StrictAssociative`

Retourne la valeur sous forme de tableau associatif.

**Retourne :** `StrictAssociative` - Tableau associatif typé

**Exemple :**
```php
$value = $indexable->getValue();
// ['model_class' => 'App\Models\User', 'id' => 123]
```

---

## Cas d'utilisation

### Cas 1 : Création d'une référence légère

```php
class IndexService
{
    public function queueIndexing(User $user): void
    {
        // Créer une référence sans charger le modèle
        $indexable = new IndexableVO(
            $user->getMorphClass(),
            $user->getKey()
        );
        
        // Envoyer à la file d'attente
        $this->taskService->dispatch($indexable);
    }
}
```

### Cas 2 : Récupération d'instance dans une tâche

```php
class IndexTask extends AbstractUniqueTask
{
    protected function process(): void
    {
        $item = $this->getPayload()->items[0];
        
        try {
            $model = $item->getInstance();
            
            if ($model->shouldBeIndexed()) {
                $this->indexer->index($model);
            }
        } catch (ModelNotFoundException $e) {
            $this->warning("Model not found: {$e->getMessage()}");
        }
    }
}
```

### Cas 3 : Collection de références pour traitement par lots

```php
$items = new IndexableVOCollection();
$items->add(new IndexableVO(User::class, 1));
$items->add(new IndexableVO(User::class, 2));
$items->add(new IndexableVO(Product::class, 10));

// Récupération optimisée (une requête par classe)
$instances = $items->getModelInstances();
```

### Cas 4 : Filtrage par classe

```php
$items = new IndexableVOCollection();
$items->add(new IndexableVO(User::class, 1));
$items->add(new IndexableVO(Admin::class, 2));
$items->add(new IndexableVO(Product::class, 10));

// Filtrer uniquement les utilisateurs
$users = $items->filterByModelClass(User::class);

// Filtrer par classes multiples
$usersAndAdmins = $items->filterByModelClasses([
    User::class,
    Admin::class
]);
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Classe inexistante | `InvalidArgumentException` | `Class {class} does not exist` |
| Classe n'implémente pas Indexable | `InvalidArgumentException` | `Class {class} must implement Indexable` |
| Modèle non trouvé | `ModelNotFoundException` | `Model {class} with ID {id} not found` |

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| Construction | O(1) | Validation simple |
| `getModelClass()` | O(1) | Accès direct |
| `getId()` | O(1) | Accès direct |
| `getInstance()` | O(1) | Une requête SQL |
| `getValue()` | O(1) | Construction de tableau |

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

use AndyDefer\LaravelIndexer\ValueObjects\IndexableVO;
use AndyDefer\LaravelIndexer\Collections\IndexableVOCollection;

// 1. Création d'un IndexableVO
$userRef = new IndexableVO('App\Models\User', 123);
echo $userRef->getModelClass(); // 'App\Models\User'
echo $userRef->getId(); // 123

// 2. Récupération de l'instance
try {
    $user = $userRef->getInstance();
    echo $user->name; // John Doe
} catch (ModelNotFoundException $e) {
    echo "Utilisateur non trouvé";
}

// 3. Utilisation en collection
$items = new IndexableVOCollection();
$items->add(new IndexableVO('App\Models\User', 1));
$items->add(new IndexableVO('App\Models\User', 2));
$items->add(new IndexableVO('App\Models\Product', 10));

// Récupération optimisée des instances
$instances = $items->getModelInstances();
foreach ($instances as $model) {
    echo get_class($model) . ':' . $model->getKey() . PHP_EOL;
}
// App\Models\User:1
// App\Models\User:2
// App\Models\Product:10

// 4. Filtrage par classe
$users = $items->filterByModelClass('App\Models\User');
echo $users->count(); // 2

// 5. Vérification d'existence
if ($items->containsId(1)) {
    echo "L'utilisateur 1 est dans la collection";
}
```

---

## Voir aussi

- `Indexable` - Interface des entités indexables
- `IndexableVOCollection` - Collection d'IndexableVO
- `ModelNotFoundException` - Exception pour modèles non trouvés
- `AbstractValueObject` - Classe parente