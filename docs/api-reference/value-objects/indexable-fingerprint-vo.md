# IndexableFingerprintVO - Référence Technique

## Description

Value Object représentant une empreinte (fingerprint) d'entité indexable. Identifie de manière unique une entité dans l'index en combinant son namespace et son ID.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── IndexableFingerprintVO
```

## Rôle principal

Ce Value Object est l'identifiant universel des documents indexés. Il :

- Encapsule le format `{namespace}|{id}`
- Garantit la validité du format à la construction
- Fournit des méthodes d'accès aux composants
- Permet les vérifications d'appartenance à un namespace

### Utilisations principales

1. **Clé primaire des documents indexés** : Identifiant unique dans `indexed_documents`
2. **Lien avec les modèles Eloquent** : Relation entre le modèle source et son index
3. **Opérations de recherche** : `exists()`, `findByFingerPrint()`, etc.
4. **Suppression ciblée** : `deleteByFingerPrint()`, `deleteByFingerprintString()`

## Format

```
{namespace}|{id}
```

- **Namespace** : Nom de classe complet (FQCN) avec backslashes
- **Séparateur** : `|` (pipe)
- **ID** : Identifiant de l'entité (string ou integer)

### Exemples valides

```
App\Models\User|123
App\Models\Product|abc-123-def
App\Models\Order|456
```

### Exemples invalides

```
App\Models\User123          // Pas de séparateur
|123                        // Namespace vide
App\Models\User|            // ID vide
```

## API / Méthodes publiques

### `__construct(string $value)`

Constructeur qui valide et parse la valeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Fingerprint brut au format `{namespace}|{id}` |

**Exceptions :** `InvalidArgumentException` si le format est invalide

**Exemple :**
```php
$fingerprint = new IndexableFingerprintVO('App\Models\User|123');
```

---

### `getId(): string`

Retourne l'ID de l'entité.

**Retourne :** `string` - ID de l'entité

**Exemple :**
```php
$id = $fingerprint->getId(); // '123'
```

---

### `getNamespace(): string`

Retourne le namespace de l'entité.

**Retourne :** `string` - Namespace (ex: `'App\Models\User'`)

**Exemple :**
```php
$namespace = $fingerprint->getNamespace(); // 'App\Models\User'
```

---

### `getValue(): string`

Retourne la valeur complète du fingerprint.

**Retourne :** `string` - Fingerprint complet

**Exemple :**
```php
$value = $fingerprint->getValue(); // 'App\Models\User|123'
```

---

### `belongsTo(string $namespace): bool`

Vérifie si le fingerprint appartient au namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à vérifier |

**Retourne :** `bool` - `true` si le fingerprint appartient au namespace

**Exemple :**
```php
if ($fingerprint->belongsTo('App\Models\User')) {
    // L'entité est un User
}
```

---

### `belongsToAny(array $namespaces): bool`

Vérifie si le fingerprint appartient à l'un des namespaces donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespaces` | `string[]` | Liste des namespaces à vérifier |

**Retourne :** `bool` - `true` si le fingerprint appartient à l'un des namespaces

**Exemple :**
```php
$allowed = ['App\Models\User', 'App\Models\Admin'];
if ($fingerprint->belongsToAny($allowed)) {
    // L'entité est un User ou un Admin
}
```

---

### `fromParts(string $namespace, string $id): self`

Crée une nouvelle instance à partir des composants namespace et ID.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace de l'entité |
| `$id` | `string` | ID de l'entité |

**Retourne :** `self` - Nouvelle instance du Value Object

**Exemple :**
```php
$fingerprint = IndexableFingerprintVO::fromParts(
    'App\Models\User',
    '123'
);
// 'App\Models\User|123'
```

---

## Cas d'utilisation

### Cas 1 : Création depuis un modèle Eloquent

```php
class User extends Model implements Indexable
{
    public function getFingerprint(): IndexableFingerprintVO
    {
        return IndexableFingerprintVO::fromParts(
            $this->getMorphClass(),
            (string) $this->getKey()
        );
    }
}

// Utilisation
$user = User::find(123);
$fingerprint = $user->getFingerprint(); // 'App\Models\User|123'
```

### Cas 2 : Vérification d'existence dans l'index

```php
class IndexService
{
    public function isIndexed(Model&Indexable $model): bool
    {
        $fingerprint = IndexableFingerprintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );
        
        return $this->documentRepository->existsByFingerPrint($fingerprint);
    }
}
```

### Cas 3 : Filtrage par namespace dans une collection

```php
$fingerprints = new IndexableFingerPrintVOCollection();
$fingerprints->add(new IndexableFingerprintVO('App\Models\User|1'));
$fingerprints->add(new IndexableFingerprintVO('App\Models\User|2'));
$fingerprints->add(new IndexableFingerprintVO('App\Models\Product|10'));

// Filtrer uniquement les utilisateurs
$users = $fingerprints->filterByNamespace('App\Models\User');
// Contient User|1 et User|2
```

### Cas 4 : Suppression ciblée

```php
class IndexDeleter
{
    public function deleteModel(Model&Indexable $model): void
    {
        $fingerprint = IndexableFingerprintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );
        
        $this->documentRepository->deleteByFingerPrint($fingerprint);
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Valeur vide | `InvalidArgumentException` | `IndexableFingerPrint cannot be empty` |
| Pas de séparateur | `InvalidArgumentException` | `Invalid format. Expected "{namespace}|{id}", got "..."` |
| Trop de séparateurs | `InvalidArgumentException` | `Invalid format. Expected "{namespace}|{id}", got "..."` |
| ID vide | `InvalidArgumentException` | `ID cannot be empty` |
| Namespace vide | `InvalidArgumentException` | `Namespace cannot be empty` |

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| Construction | O(1) | Validation et parsing simple |
| `getId()` | O(1) | Accès direct |
| `getNamespace()` | O(1) | Accès direct |
| `getValue()` | O(1) | Concaténation simple |
| `belongsTo()` | O(1) | Comparaison de chaînes |
| `belongsToAny()` | O(n) | n = nombre de namespaces |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;

// 1. Création directe
$fingerprint = new IndexableFingerprintVO('App\Models\User|123');
echo $fingerprint->getValue(); // 'App\Models\User|123'

// 2. Création via fromParts()
$fingerprint = IndexableFingerprintVO::fromParts('App\Models\User', '456');
echo $fingerprint->getValue(); // 'App\Models\User|456'

// 3. Accès aux composants
$namespace = $fingerprint->getNamespace(); // 'App\Models\User'
$id = $fingerprint->getId(); // '456'

// 4. Vérification d'appartenance
$isUser = $fingerprint->belongsTo('App\Models\User'); // true
$isProduct = $fingerprint->belongsTo('App\Models\Product'); // false

// 5. Vérification multiple
$allowed = ['App\Models\User', 'App\Models\Admin'];
$isAllowed = $fingerprint->belongsToAny($allowed); // true

// 6. Utilisation dans une collection
$collection = new IndexableFingerPrintVOCollection();
$collection->add(new IndexableFingerprintVO('App\Models\User|1'));
$collection->add(new IndexableFingerprintVO('App\Models\User|2'));
$collection->add(new IndexableFingerprintVO('App\Models\Product|10'));

$users = $collection->filterByNamespace('App\Models\User');
echo $users->count(); // 2
```

---

## Voir aussi

- `Indexable` - Interface des entités indexables
- `IndexableFingerPrintVOCollection` - Collection de fingerprints
- `IndexedDocumentRepositoryInterface` - Repository utilisant les fingerprints
- `IndexableRecordFactory` - Factory créant des fingerprints