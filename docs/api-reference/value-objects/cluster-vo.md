# ClusterVO - Référence Technique

## Description

Value Object représentant un cluster de filtrage pour l'indexation et la recherche de documents dans Laravel Indexer.

## Hiérarchie

```
AbstractValueObject
    └── ClusterVO
```

## Rôle principal

Le `ClusterVO` sert à structurer des paires clé-valeur pour :

- **Stockage** : Attacher des métadonnées contextuelles à un document indexé
- **Recherche** : Filtrer les documents par contexte avec un mode logique AND ou OR

## Format

### Stockage (sans mode)
```
key1:value1|key2:value2|key3:value3
```

### Recherche (avec mode)
```
key1:value1|key2:value2|key3:value3@AND
key1:value1|key2:value2|key3:value3@OR
```

### Caractères autorisés
- **Clés** : `a-z`, `A-Z`, `0-9`, `_` uniquement
- **Valeurs** : Tous les caractères (libre)

### Séparateurs réservés
- `:` = séparateur clé/valeur
- `|` = séparateur de paires
- `@` = séparateur de mode

## API / Méthodes publiques

### `__construct(string $value): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Chaîne représentant le cluster |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` - Format invalide

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor|status:active');
$cluster = new ClusterVO('type:user|role:doctor@AND');
```

---

### `get(string $key): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé recherchée |

**Retourne :** `?string` - Valeur associée ou `null`

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor');
$type = $cluster->get('type');  // 'user'
$unknown = $cluster->get('unknown'); // null
```

---

### `has(string $key): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé à vérifier |

**Retourne :** `bool` - `true` si la clé existe

---

### `getMode(): ?string`

**Retourne :** `?string` - `'AND'`, `'OR'` ou `null`

---

### `hasMode(): bool`

**Retourne :** `bool` - `true` si un mode est défini

---

### `isAnd(): bool`

**Retourne :** `bool` - `true` si le mode est `AND`

---

### `isOr(): bool`

**Retourne :** `bool` - `true` si le mode est `OR`

---

### `getValue(): string`

**Retourne :** `string` - La chaîne complète du cluster

---

### `getClusterPart(): string`

**Retourne :** `string` - La partie du cluster sans le mode

**Exemple :**
```php
$cluster = new ClusterVO('type:user|role:doctor@AND');
$cluster->getClusterPart(); // 'type:user|role:doctor'
```

---

### `all(): array`

**Retourne :** `array<string, string>` - Toutes les paires clé-valeur

---

### `with(string $key, string $value): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé (alphanumérique + `_`) |
| `$value` | `string` | Valeur (libre) |

**Retourne :** `self` - Nouvelle instance

**Exceptions :** `InvalidArgumentException` - Clé invalide

**Exemple :**
```php
$cluster = new ClusterVO('type:user')
    ->with('role', 'doctor')
    ->with('status', 'active');
// 'type:user|role:doctor|status:active'
```

---

### `withIf(bool $condition, string $key, string $value): self`

Ajoute une paire si la condition est `true`.

**Exemple :**
```php
$cluster = new ClusterVO('type:user')
    ->withIf($isAdmin, 'role', 'admin')
    ->withIf($isVerified, 'verified', 'true');
```

---

### `without(string $key): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé à supprimer |

**Retourne :** `self` - Nouvelle instance

**Exceptions :** `InvalidArgumentException` - Si le cluster devient vide

---

### `hasAll(array $keys): bool`

Vérifie si toutes les clés existent.

---

### `hasAny(array $keys): bool`

Vérifie si au moins une clé existe.

---

### `static make(string $key, string $value, ?string $mode = null): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé |
| `$value` | `string` | Valeur |
| `$mode` | `string|null` | `AND`, `OR` ou `null` |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
// Stockage (sans mode)
$cluster = ClusterVO::make('type', 'user');
// 'type:user'

// Recherche (avec mode)
$cluster = ClusterVO::make('type', 'user', 'AND');
// 'type:user@AND'
```

---

### `withMode(string $mode): self`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$mode` | `string` | `AND` ou `OR` |

**Retourne :** `self` - Nouvelle instance

**Exceptions :** `InvalidArgumentException` - Mode invalide

---

### `toAnd(): self`

**Retourne :** `self` - Nouvelle instance avec le mode `AND`

---

### `toOr(): self`

**Retourne :** `self` - Nouvelle instance avec le mode `OR`

---

### `whenNotEmpty(string $key, mixed $value): self`

Ajoute la paire si la valeur n'est pas vide.

---

### `whenBool(string $key, mixed $value): self`

Ajoute la paire si la valeur est un booléen.

---

### `whenNotNull(string $key, mixed $value): self`

Ajoute la paire si la valeur n'est pas `null`.

---

### `whenNumeric(string $key, mixed $value): self`

Ajoute la paire si la valeur est numérique.

---

### `withTernary(string $key, bool $condition, string $trueValue, string $falseValue): self`

Ajoute une paire avec une valeur conditionnelle.

**Exemple :**
```php
$cluster = new ClusterVO('type:user')
    ->withTernary('status', $user->is_active, 'active', 'inactive');
// Si true → 'status:active', si false → 'status:inactive'
```

---

### `withCases(string $prefix, array $values, string $suffix = ''): self`

Ajoute des paires pour chaque valeur d'un tableau. Format : `prefix_{value}{suffix}:true`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe de la clé |
| `$values` | `array<string>` | Liste des valeurs |
| `$suffix` | `string` | Suffixe de la clé (optionnel) |

**Exemple :**
```php
$languages = ['fr', 'en', 'lu'];
$cluster = new ClusterVO('type:user')
    ->withCases('lang_', $languages);
// 'type:user|lang_fr:true|lang_en:true|lang_lu:true'
```

---

### `withEnum(string $prefix, string $enumClass, string $suffix = ''): self`

Ajoute des paires pour chaque case d'un enum `UnitEnum`. Utilise le nom du case en minuscules.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Préfixe de la clé |
| `$enumClass` | `class-string<\UnitEnum>` | Classe de l'enum |
| `$suffix` | `string` | Suffixe de la clé (optionnel) |

**Exemple :**
```php
$cluster = new ClusterVO('type:user')
    ->withEnum('role_', UserType::class);
// 'type:user|role_patient:true|role_doctor:true|role_admin:true|role_staff:true'
```

---

### `withEnumValues(string $prefix, string $enumClass, string $suffix = ''): self`

Ajoute des paires pour les valeurs d'un enum. Pour `BackedEnum`, utilise `$case->value`. Pour `UnitEnum`, utilise `$case->name`. Tout est converti en minuscules.

**Exemple :**
```php
$cluster = new ClusterVO('type:user')
    ->withEnumValues('status_', UserStatus::class);
// 'type:user|status_active:true|status_inactive:true|status_pending:true|status_banned:true'
```

---

### `applyToQuery(Builder $query, string $column = 'cluster'): void`

Applique les conditions de cluster à une requête Eloquent.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | Requête Eloquent |
| `$column` | `string` | Nom de la colonne (défaut: `cluster`) |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` - Si le cluster n'a pas de mode

**Exemple :**
```php
$cluster = new ClusterVO('type:user|status:active@AND');
$query = IndexedDocument::query();
$cluster->applyToQuery($query);
// WHERE cluster LIKE '%type:user%' AND cluster LIKE '%status:active%'
```

---

### `toArray(): array`

**Retourne :** `array<string, string>` - Les paires clé-valeur

---

### `__toString(): string`

**Retourne :** `string` - La chaîne complète du cluster

---

## Cas d'utilisation

### Cas 1 : Stockage - Indexation d'un document

```php
$user = User::find(1);
$cluster = new ClusterVO('type:user')
    ->withTernary('status', $user->is_active, 'active', 'inactive')
    ->with('role', $user->role)
    ->whenNotEmpty('city', $user->city);
// 'type:user|status:active|role:doctor|city:Paris'
```

### Cas 2 : Recherche avec filtre AND

```php
$cluster = new ClusterVO('type:user|status:active|role:doctor@AND');
$query = IndexedDocument::query();
$cluster->applyToQuery($query);
// WHERE cluster LIKE '%type:user%' 
//   AND cluster LIKE '%status:active%' 
//   AND cluster LIKE '%role:doctor%'
```

### Cas 3 : Recherche avec filtre OR

```php
$cluster = new ClusterVO('role_doctor:true|role_admin:true@OR');
$query = IndexedDocument::query();
$cluster->applyToQuery($query);
// WHERE cluster LIKE '%role_doctor:true%' 
//    OR cluster LIKE '%role_admin:true%'
```

### Cas 4 : Utilisation des enums

```php
$languages = ['fr', 'en', 'lu'];
$cluster = new ClusterVO('type:user')
    ->withCases('lang_', $languages)
    ->withEnum('role_', UserType::class)
    ->withTernary('status', $user->is_active, 'active', 'inactive');
// 'type:user|lang_fr:true|lang_en:true|lang_lu:true|role_patient:true|role_doctor:true|status:active'
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Cluster vide | `InvalidArgumentException` | `Cluster value cannot be empty` |
| Format invalide | `InvalidArgumentException` | `Invalid cluster format. Expected "key:value|key:value@AND", got "..."` |
| Mode invalide | `InvalidArgumentException` | `Invalid mode. Expected "AND" or "OR", got "..."` |
| Paire sans clé | `InvalidArgumentException` | `Cluster key cannot be empty` |
| Paire sans valeur | `InvalidArgumentException` | `Cluster value cannot be empty for key "..."` |
| Clé invalide | `InvalidArgumentException` | `Cluster key "..." must contain only alphanumeric characters and underscore (a-z, A-Z, 0-9, _)` |
| Application sans mode | `InvalidArgumentException` | `Cluster must have a mode (AND or OR) to apply to query` |
| Enum inexistant | `InvalidArgumentException` | `Enum class "..." does not exist` |

---

## Intégration

`ClusterVO` est utilisé par :

- `IndexableRecordFactory` - Création de documents indexables
- `IndexedDocument` - Modèle de document (stockage du cluster)
- `IndexedToken` - Accès au cluster du document parent
- `IndexSearcher` - Filtrage des résultats de recherche
- `IndexedDocumentRepository` - Recherche par cluster

---

## Performance

- Opérations en O(1) pour l'accès aux clés
- Construction : parse la chaîne une seule fois
- Validation : vérifie chaque paire à la construction
- `applyToQuery()` : génère des conditions `LIKE` optimisées

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x+ | ✅ Complet |
| Laravel 11.x+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Enums\UserType;
use AndyDefer\LaravelIndexer\Tests\Fixtures\Enums\UserStatus;

// 1. Stockage (sans mode)
$storage = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->withTernary('status', true, 'active', 'inactive')
    ->withCases('lang_', ['fr', 'en', 'lu'])
    ->whenNotEmpty('city', 'Paris');

echo $storage->getValue();
// 'type:user|role:doctor|status:active|lang_fr:true|lang_en:true|lang_lu:true|city:Paris'

// 2. Recherche AND
$searchAnd = ClusterVO::make('type', 'user', 'AND')
    ->with('status', 'active')
    ->with('role', 'doctor');

$searchAnd->applyToQuery($query);
// WHERE cluster LIKE '%type:user%' AND cluster LIKE '%status:active%' AND cluster LIKE '%role:doctor%'

// 3. Recherche OR avec enum
$searchOr = ClusterVO::make('status', 'active', 'OR')
    ->withEnum('role_', UserType::class);

$searchOr->applyToQuery($query);
// WHERE cluster LIKE '%status:active%' 
//   AND (cluster LIKE '%role_patient:true%' 
//    OR cluster LIKE '%role_doctor:true%' 
//    OR cluster LIKE '%role_admin:true%' 
//    OR cluster LIKE '%role_staff:true%')

// 4. Vérification
if ($searchAnd->hasMode()) {
    echo $searchAnd->getMode(); // 'AND'
}

// 5. Parcours
foreach ($searchAnd->all() as $key => $value) {
    echo $key . ':' . $value;
}
// 'type:user', 'status:active', 'role:doctor'
```

## Voir aussi

- `IndexableRecordFactory` - Factory de création de documents
- `IndexedDocument` - Modèle de document indexé
- `IndexSearcher` - Service de recherche
- `IndexedDocumentRepository` - Repository de documents
- [Laravel Indexer - Documentation](https://github.com/andydefer/laravel-indexer)