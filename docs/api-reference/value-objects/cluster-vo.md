# ClusterVO - Référence Technique

## Description

Value Object représentant un cluster pour le regroupement et le filtrage de données. Un cluster est une chaîne structurée au format `key1:value1|key2:value2,value3` qui permet d'attacher des métadonnées contextuelles à des documents indexés.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── ClusterVO
```

**Lien source :** [ClusterVO.php](https://github.com/andydefer/laravel-indexer/blob/main/src/ValueObjects/ClusterVO.php)

## Rôle principal

Le `ClusterVO` permet de :

1. **Catégoriser** les documents indexés (ex: `type:user|status:active`)
2. **Filtrer** les recherches par contexte (ex: `tenant:company_abc`)
3. **Multi-tenant** : isoler les données par client ou environnement
4. **Recherche contextuelle** : affiner les résultats avec des critères précis

---

## Format du cluster

```
key1:value1|key2:value2,value3|key3:value4,value5,value6
```

| Élément | Séparateur | Description |
|---------|------------|-------------|
| `key:value` | `:` | Une paire clé-valeur |
| `\|` | `\|` | Séparateur entre les paires (AND) |
| `,` | `,` | Séparateur entre les valeurs (OR) |

---

## API / Méthodes publiques

### `__construct(string $value)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Chaîne au format `key1:value1\|key2:value2,value3` |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` si le format est invalide

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor|status:active');
$cluster = new ClusterVO(''); // Cluster vide
```

---

### `get(string $key): array`

Récupère les valeurs associées à une clé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à rechercher |

**Retourne :** `array<string>` - Liste des valeurs, ou tableau vide si la clé n'existe pas

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor,admin');
$roles = $cluster->get('role'); // ['doctor', 'admin']
$unknown = $cluster->get('unknown'); // []
```

---

### `getFirst(string $key): ?string`

Récupère la première valeur associée à une clé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à rechercher |

**Retourne :** `?string` - La première valeur, ou `null` si la clé n'existe pas

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor,admin');
$firstRole = $cluster->getFirst('role'); // 'doctor'
```

---

### `has(string $key): bool`

Vérifie si une clé existe dans le cluster.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à vérifier |

**Retourne :** `bool` - `true` si la clé existe, `false` sinon

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor');
$cluster->has('type');  // true
$cluster->has('role');  // true
$cluster->has('status'); // false
```

---

### `contains(string $key, string $value): bool`

Vérifie si une clé contient une valeur spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à vérifier |
| `$value` | `string` | La valeur à rechercher |

**Retourne :** `bool` - `true` si la valeur existe, `false` sinon

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor,admin');
$cluster->contains('role', 'doctor'); // true
$cluster->contains('role', 'guest');  // false
```

---

### `all(): array`

Récupère toutes les paires clé-valeur.

**Retourne :** `array<string, string[]>` - Toutes les paires du cluster

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor,admin');
$cluster->all(); // ['type' => ['user'], 'role' => ['doctor', 'admin']]
```

---

### `getValue(): string`

Récupère la valeur brute du cluster.

**Retourne :** `string` - La chaîne originale du cluster

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor');
$cluster->getValue(); // 'type:user|role:doctor'
```

---

### `with(string $key, string $value): self`

Ajoute une paire clé-valeur (retourne une nouvelle instance immuable).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$value` | `string` | La valeur à ajouter |

**Retourne :** `self` - Nouvelle instance avec la paire ajoutée

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->with('status', 'active');
// 'type:user|role:doctor|status:active'
```

---

### `withIf(bool $condition, string $key, string $value): self`

Ajoute une paire seulement si la condition est vraie.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$condition` | `bool` | Condition à vérifier |
| `$key` | `string` | La clé à ajouter |
| `$value` | `string` | La valeur à ajouter |

**Retourne :** `self` - Nouvelle instance (inchangée si la condition est fausse)

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->withIf($user->isAdmin, 'role', 'admin')
    ->withIf($user->isActive, 'status', 'active');
```

---

### `withDefault(string $key, mixed $value, string $default): self`

Ajoute une paire avec une valeur par défaut si la valeur est `null` ou une chaîne vide. `false`, `0`, `'0'` sont considérés comme des valeurs valides.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$value` | `mixed` | La valeur à tester |
| `$default` | `string` | Valeur par défaut si `$value` est vide |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->withDefault('role', $user->role, 'guest')
    ->withDefault('status', $user->status, 'pending');
```

---

### `withTernary(string $key, bool $condition, string $trueValue, string $falseValue): self`

Ajoute une paire basée sur une condition ternaire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$condition` | `bool` | Condition à évaluer |
| `$trueValue` | `string` | Valeur si `$condition` est `true` |
| `$falseValue` | `string` | Valeur si `$condition` est `false` |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->withTernary('status', $user->isActive, 'active', 'inactive')
    ->withTernary('verified', $user->isVerified, 'true', 'false');
```

---

### `withMany(string $key, array $values): self`

Ajoute plusieurs valeurs à une clé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$values` | `array<string>` | Liste des valeurs à ajouter |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->withMany('specialty', ['cardiologie', 'neurologie']);
// 'type:user|specialty:cardiologie,neurologie'
```

---

### `withManyIf(bool $condition, string $key, array $values): self`

Ajoute plusieurs valeurs seulement si la condition est vraie.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$condition` | `bool` | Condition à vérifier |
| `$key` | `string` | La clé à ajouter |
| `$values` | `array<string>` | Liste des valeurs à ajouter |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->withManyIf(!empty($user->specialties), 'specialty', $user->specialties);
```

---

### `without(string $key, ?string $value = null): self`

Supprime une valeur ou toute une clé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à modifier |
| `$value` | `?string` | Valeur à supprimer (si `null`, supprime toute la clé) |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor,admin');
$newCluster = $cluster->without('role', 'admin');
// 'type:user|role:doctor'

$newCluster = $cluster->without('role');
// 'type:user'
```

---

### `hasAll(array $keys): bool`

Vérifie si toutes les clés existent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$keys` | `array<string>` | Liste des clés à vérifier |

**Retourne :** `bool` - `true` si toutes les clés existent

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor|status:active');
$cluster->hasAll(['type', 'role']); // true
$cluster->hasAll(['type', 'unknown']); // false
```

---

### `hasAny(array $keys): bool`

Vérifie si au moins une des clés existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$keys` | `array<string>` | Liste des clés à vérifier |

**Retourne :** `bool` - `true` si au moins une clé existe

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor');
$cluster->hasAny(['type', 'unknown']); // true
$cluster->hasAny(['unknown1', 'unknown2']); // false
```

---

### `static make(string $key, string $value): self`

Crée un cluster avec une seule paire clé-valeur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé |
| `$value` | `string` | La valeur |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->with('status', 'active');
// 'type:user|status:active'
```

---

### `static fromPairs(array $pairs): self`

Crée un cluster à partir d'un tableau de paires.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$pairs` | `array<string, string|string[]>` | Tableau de paires clé-valeur |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$cluster = ClusterVO::fromPairs([
    'type' => 'user',
    'role' => ['doctor', 'admin'],
    'status' => 'active',
]);
// 'type:user|role:doctor,admin|status:active'
```

---

### `whenNotEmpty(string $key, mixed $value): self`

Ajoute une paire si la valeur n'est pas vide. `false`, `0`, `'0'` sont considérés comme des valeurs valides.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$value` | `mixed` | La valeur à tester |

**Retourne :** `self` - Nouvelle instance (inchangée si la valeur est vide)

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->whenNotEmpty('city', $user->city)
    ->whenNotEmpty('country', $user->country);
```

---

### `whenNotNull(string $key, mixed $value): self`

Ajoute une paire si la valeur n'est pas `null`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$value` | `mixed` | La valeur à tester |

**Retourne :** `self` - Nouvelle instance (inchangée si `null`)

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->whenNotNull('role', $user->role);
```

---

### `whenKeyExists(string $key, array $array, string $arrayKey): self`

Ajoute une paire si la clé existe dans un tableau et que la valeur n'est pas vide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$array` | `array` | Le tableau à inspecter |
| `$arrayKey` | `string` | La clé à rechercher dans le tableau |

**Retourne :** `self` - Nouvelle instance (inchangée si la clé n'existe pas ou est vide)

**Exemple :**
```php
$metadata = ['role' => 'doctor', 'tenant' => 'company_abc'];

$cluster = ClusterVO::make('type', 'user')
    ->whenKeyExists('role', $metadata, 'role')
    ->whenKeyExists('tenant', $metadata, 'tenant');
// 'type:user|role:doctor|tenant:company_abc'
```

---

### `whenArrayNotEmpty(string $key, array $values, string $separator = ','): self`

Ajoute une paire avec des valeurs séparées par un séparateur si le tableau n'est pas vide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$values` | `array<string>` | Liste des valeurs |
| `$separator` | `string` | Séparateur (défaut: `,`) |

**Retourne :** `self` - Nouvelle instance (inchangée si le tableau est vide)

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->whenArrayNotEmpty('tags', ['php', 'laravel', 'react']);
// 'type:user|tags:php,laravel,react'

$cluster = ClusterVO::make('type', 'user')
    ->whenArrayNotEmpty('tags', ['php', 'laravel', 'react'], ';');
// 'type:user|tags:php;laravel;react'
```

---

### `whenNumeric(string $key, mixed $value): self`

Ajoute une paire si la valeur est numérique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$value` | `mixed` | La valeur à tester |

**Retourne :** `self` - Nouvelle instance (inchangée si non numérique)

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->whenNumeric('age', 30)
    ->whenNumeric('score', '95.5');
// 'type:user|age:30|score:95.5'
```

---

### `whenBool(string $key, mixed $value): self`

Ajoute une paire si la valeur est un booléen.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé à ajouter |
| `$value` | `mixed` | La valeur à tester |

**Retourne :** `self` - Nouvelle instance (inchangée si non booléen)

**Exemple :**
```php
$cluster = ClusterVO::make('type', 'user')
    ->whenBool('verified', true)
    ->whenBool('premium', false);
// 'type:user|verified:true|premium:false'
```

---

### `toArray(): array`

Convertit le cluster en tableau.

**Retourne :** `array<string, string[]>` - Représentation en tableau

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor,admin');
$cluster->toArray(); // ['type' => ['user'], 'role' => ['doctor', 'admin']]
```

---

### `__toString(): string`

Convertit le cluster en chaîne.

**Retourne :** `string` - La valeur brute du cluster

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor');
echo $cluster; // 'type:user|role:doctor'
```

---

## Cas d'utilisation

### Cas 1 : Construction d'un cluster pour un utilisateur

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

class User extends Model implements Indexable
{
    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'user')
            ->withDefault('role', $this->role, 'guest')
            ->withTernary('status', $this->is_active, 'active', 'inactive')
            ->whenNotEmpty('city', $this->city)
            ->whenBool('verified', $this->is_verified)
            ->whenArrayNotEmpty('tags', $this->tags);
    }
}

// Résultat : 'type:user|role:admin|status:active|city:Paris|verified:true|tags:php,laravel'
```

---

### Cas 2 : Construction d'un cluster pour un médecin

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

class Doctor extends Model implements Indexable
{
    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'doctor')
            ->withTernary('status', $this->is_active, 'active', 'inactive')
            ->whenNotEmpty('specialty', $this->specialty)
            ->whenNotEmpty('city', $this->city)
            ->whenBool('verified', $this->is_verified)
            ->whenBool('available', $this->is_accepting_patients)
            ->whenNumeric('experience', $this->years_experience);
    }
}

// Résultat : 'type:doctor|status:active|specialty:cardiologie|city:Kinshasa|verified:true|available:true|experience:10'
```

---

### Cas 3 : Filtrage multi-tenant

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

class TenantAwareModel extends Model implements Indexable
{
    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::make('type', 'tenant_aware')
            ->with('tenant', $this->tenant_id)
            ->with('environment', app()->environment())
            ->withTernary('is_production', app()->isProduction(), 'true', 'false');
    }
}

// Résultat : 'type:tenant_aware|tenant:company_abc|environment:production|is_production:true'
```

---

### Cas 4 : Construction conditionnelle complexe

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

class ComplexModel extends Model implements Indexable
{
    public function getIndexableCluster(): ClusterVO
    {
        $cluster = ClusterVO::make('type', 'complex');

        // Conditions multiples
        $cluster = $cluster
            ->with('status', $this->is_active ? 'active' : 'inactive')
            ->whenNotEmpty('category', $this->category)
            ->whenNotNull('subcategory', $this->subcategory)
            ->whenKeyExists('tenant', $this->metadata, 'tenant')
            ->whenArrayNotEmpty('tags', $this->tags, '|')
            ->whenNumeric('priority', $this->priority)
            ->whenBool('featured', $this->is_featured);

        return $cluster;
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Format invalide (pas de `:`) | `InvalidArgumentException` | `Invalid cluster format. Expected "key:value", got "{value}"` |
| Clé vide | `InvalidArgumentException` | `Cluster key cannot be empty` |
| Valeur vide | `InvalidArgumentException` | `Cluster values cannot be empty for key "{key}"` |
| Valeur vide dans une liste | `InvalidArgumentException` | `Empty value not allowed for key "{key}"` |

---

## Performance

- **Parsing** : O(n) où n est le nombre de paires, effectué une seule fois à la construction
- **Mémoire** : Stocke les valeurs dans un tableau associatif indexé
- **Immutabilité** : Chaque opération crée une nouvelle instance, à utiliser avec modération dans des boucles

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

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

// Construction fluide
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->with('status', 'active')
    ->withMany('specialties', ['cardiologie', 'neurologie'])
    ->withIf($isVerified, 'verified', 'true')
    ->withDefault('language', $user->language, 'fr')
    ->withTernary('premium', $user->isPremium, 'yes', 'no')
    ->whenNotEmpty('city', $user->city)
    ->whenNotNull('country', $user->country)
    ->whenKeyExists('tenant', $metadata, 'tenant')
    ->whenArrayNotEmpty('tags', $user->tags)
    ->whenNumeric('age', $user->age)
    ->whenBool('active', $user->isActive);

// Résultat :
// 'type:user|role:doctor|status:active|specialties:cardiologie,neurologie|verified:true|language:fr|premium:yes|city:Paris|country:France|tenant:company_abc|tags:php,laravel|age:30|active:true'

// Lecture des valeurs
$type = $cluster->getFirst('type'); // 'user'
$roles = $cluster->get('role'); // ['doctor']
$hasTenant = $cluster->has('tenant'); // true
$hasTags = $cluster->contains('tags', 'php'); // true

// Suppression d'une valeur
$newCluster = $cluster->without('role', 'doctor');
// 'type:user|status:active|specialties:cardiologie,neurologie|...'

// Suppression d'une clé entière
$newCluster = $cluster->without('tags');
// 'type:user|role:doctor|status:active|specialties:cardiologie,neurologie|...'

// Conversion
$array = $cluster->toArray();
$string = (string) $cluster;
```

---

## Voir aussi

- `AbstractValueObject` - Classe de base pour les Value Objects
- `Indexable` - Interface pour les modèles indexables
- `IndexableRecordFactory` - Factory pour créer des enregistrements indexables
- `ContextFilterVO` - Filtre de contexte pour Laravel Hermes