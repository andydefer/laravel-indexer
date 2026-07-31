# SearchQueryVO - Référence Technique

## Description

Value Object représentant une requête de recherche. Parse une chaîne de requête au format `ngram1=field1,field2|ngram2=field3|ngram3=field1,field4` et fournit des méthodes d'accès structurées.

## Hiérarchie / Implémentations

```
AbstractValueObject
    └── SearchQueryVO
```

## Rôle principal

Ce Value Object est le format de requête du système de recherche. Il :

- Parse une chaîne de requête textuelle
- Extrait les n-grams et leurs champs associés
- Fournit des méthodes d'interrogation structurées
- Garantit la validité du format à la construction

### Syntaxe

```
ngram1=field1,field2|ngram2=field3|ngram3=field1,field4
```

- **N-gram** : Terme recherché (ex: `john`)
- **Champs** : Liste des champs à rechercher, séparés par des virgules (ex: `name,email`)
- **Séparateur de groupe** : `|` entre chaque condition
- **Séparateur n-gram/champs** : `=` entre le terme et les champs

### Exemples

```
# Recherche simple
john=name

# Recherche multi-champs
john=name,description,email

# Recherche multi-grammes (AND logique)
john=name|doe=last_name

# Recherche complexe
john=name,description|doe=last_name|admin=role
```

## API / Méthodes publiques

### `__construct(string $value)`

Constructeur qui valide et parse la requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Requête brute au format défini |

**Exceptions :** `InvalidArgumentException` si le format est invalide

**Exemple :**
```php
$query = new SearchQueryVO('john=name,email|doe=last_name');
```

---

### `getValue(): StrictAssociative`

Retourne la requête parsée sous forme de tableau associatif typé.

**Retourne :** `StrictAssociative<string, string[]>` - Tableau `[ngram => [field1, field2, ...]]`

**Exemple :**
```php
$parsed = $query->getValue();
// ['john' => ['name', 'email'], 'doe' => ['last_name']]
```

---

### `getNgrams(): array`

Retourne tous les n-grams de la requête.

**Retourne :** `string[]` - Liste des n-grams

**Exemple :**
```php
$ngrams = $query->getNgrams(); // ['john', 'doe', 'admin']
```

---

### `getFieldsForNgram(string $ngram): array`

Retourne les champs associés à un n-gram spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngram` | `string` | N-gram à rechercher |

**Retourne :** `string[]` - Liste des champs associés

**Exemple :**
```php
$fields = $query->getFieldsForNgram('john'); // ['name', 'email']
$empty = $query->getFieldsForNgram('unknown'); // []
```

---

### `hasNgram(string $ngram): bool`

Vérifie si la requête contient un n-gram spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngram` | `string` | N-gram à vérifier |

**Retourne :** `bool` - `true` si le n-gram existe

**Exemple :**
```php
if ($query->hasNgram('john')) {
    // Le terme 'john' est recherché
}
```

---

### `hasFieldForNgram(string $ngram, string $field): bool`

Vérifie si un champ spécifique est recherché pour un n-gram donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngram` | `string` | N-gram à vérifier |
| `$field` | `string` | Champ à vérifier |

**Retourne :** `bool` - `true` si le champ est associé au n-gram

**Exemple :**
```php
if ($query->hasFieldForNgram('john', 'email')) {
    // 'john' est recherché dans 'email'
}
```

---

### `contains(string $ngram, array $fields): bool`

Vérifie si la requête contient un n-gram avec tous les champs donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngram` | `string` | N-gram à vérifier |
| `$fields` | `string[]` | Champs qui doivent être présents |

**Retourne :** `bool` - `true` si le n-gram existe avec tous les champs

**Exemple :**
```php
if ($query->contains('john', ['name', 'email'])) {
    // 'john' est recherché dans 'name' ET 'email'
}
```

---

### `count(): int`

Retourne le nombre total de conditions de recherche (n-grams).

**Retourne :** `int` - Nombre de n-grams

**Exemple :**
```php
$count = $query->count(); // 3
```

---

### `getAllFields(): array`

Retourne tous les champs uniques utilisés dans la requête.

**Retourne :** `string[]` - Liste des champs uniques

**Exemple :**
```php
$fields = $query->getAllFields(); // ['name', 'email', 'last_name', 'role']
```

---

### `isEmpty(): bool`

Vérifie si la requête est vide.

**Retourne :** `bool` - `true` si la requête n'a pas de n-grams

**Exemple :**
```php
if ($query->isEmpty()) {
    // Aucun terme de recherche
}
```

---

### `getRaw(): string`

Retourne la chaîne brute de la requête.

**Retourne :** `string` - Requête brute

**Exemple :**
```php
$raw = $query->getRaw(); // 'john=name,email|doe=last_name'
```

---

## Cas d'utilisation

### Cas 1 : Recherche simple

```php
$query = new SearchQueryVO('john=name');
$ngrams = $query->getNgrams(); // ['john']
$fields = $query->getFieldsForNgram('john'); // ['name']
```

### Cas 2 : Recherche multi-champs

```php
$query = new SearchQueryVO('john=name,email,description');
$fields = $query->getFieldsForNgram('john'); // ['name', 'email', 'description']
```

### Cas 3 : Recherche multi-termes (AND logique)

```php
$query = new SearchQueryVO('john=name|doe=last_name');
$ngrams = $query->getNgrams(); // ['john', 'doe']

// La recherche doit trouver des documents contenant BOTH 'john' AND 'doe'
```

### Cas 4 : Construction de requête dynamique

```php
class SearchService
{
    public function buildQuery(array $terms): SearchQueryVO
    {
        $parts = [];
        foreach ($terms as $term => $fields) {
            $parts[] = $term . '=' . implode(',', $fields);
        }
        
        return new SearchQueryVO(implode('|', $parts));
    }
}

$query = $service->buildQuery([
    'john' => ['name', 'email'],
    'doe' => ['last_name']
]);
// 'john=name,email|doe=last_name'
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Requête vide | `InvalidArgumentException` | `Search query cannot be empty` |
| Format invalide (pas de `=`) | `InvalidArgumentException` | `Invalid format. Expected "ngram=field1,field2", got "..."` |
| N-gram vide | `InvalidArgumentException` | `N-gram cannot be empty` |
| Champs vides | `InvalidArgumentException` | `Fields cannot be empty` |
| Champ vide dans une liste | `InvalidArgumentException` | `Field cannot be empty in "..."` |

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| Construction | O(n * m) | n = groupes, m = champs par groupe |
| `getValue()` | O(1) | Accès direct |
| `getNgrams()` | O(n) | n = nombre de n-grams |
| `getFieldsForNgram()` | O(1) | Accès direct par clé |
| `hasNgram()` | O(1) | Accès direct par clé |
| `hasFieldForNgram()` | O(m) | m = nombre de champs pour le n-gram |
| `contains()` | O(m) | m = nombre de champs à vérifier |
| `getAllFields()` | O(n * m) | n = n-grams, m = champs par n-gram |

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

use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

// 1. Création d'une requête
$query = new SearchQueryVO('john=name,email|doe=last_name|admin=role,permissions');

// 2. Accès aux n-grams
$ngrams = $query->getNgrams();
// ['john', 'doe', 'admin']

// 3. Accès aux champs par n-gram
$johnFields = $query->getFieldsForNgram('john');
// ['name', 'email']

$doeFields = $query->getFieldsForNgram('doe');
// ['last_name']

$adminFields = $query->getFieldsForNgram('admin');
// ['role', 'permissions']

// 4. Vérifications
$hasJohn = $query->hasNgram('john'); // true
$hasUnknown = $query->hasNgram('unknown'); // false

$hasEmail = $query->hasFieldForNgram('john', 'email'); // true
$hasPhone = $query->hasFieldForNgram('john', 'phone'); // false

$contains = $query->contains('john', ['name', 'email']); // true
$containsMissing = $query->contains('john', ['name', 'phone']); // false

// 5. Tous les champs uniques
$allFields = $query->getAllFields();
// ['name', 'email', 'last_name', 'role', 'permissions']

// 6. Comptage
$count = $query->count(); // 3

// 7. Requête brute
$raw = $query->getRaw(); // 'john=name,email|doe=last_name|admin=role,permissions'

// 8. Utilisation dans la recherche
$searchService = new SearchService();
$results = $searchService->search($query);
```

---

## Voir aussi

- `SearchQueryRecord` - Record contenant le SearchQueryVO
- `IndexSearcher` - Service utilisant la requête
- `IndexableSearchResultCollection` - Collection de résultats
- `GramType` - Énumération des types de tokens