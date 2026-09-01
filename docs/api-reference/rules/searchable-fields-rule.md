# SearchableFieldsRule - Référence Technique

## Description

Valide qu'un tableau de champs correspond aux champs indexables d'un modèle Eloquent implémentant l'interface `Indexable`.

## Hiérarchie / Implémentations

```
Illuminate\Contracts\Validation\ValidationRule
    └── SearchableFieldsRule
```

## Rôle principal

Cette règle de validation Laravel garantit que les champs soumis dans une requête API sont bien des champs indexables pour le modèle spécifié. Elle utilise l'analyse AST (Abstract Syntax Tree) du code source pour découvrir les champs sans instancier le modèle.

## API / Méthodes publiques

### `__construct(string $modelClass)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `class-string<Indexable>` | FQCN du modèle Eloquent qui implémente `Indexable` |

**Description :** Constructeur qui reçoit la classe du modèle à valider.

**Exemple :**
```php
$rule = new SearchableFieldsRule(User::class);
```

---

### `validate(string $attribute, mixed $value, Closure $fail): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$attribute` | `string` | Nom du champ validé (ex: 'fields') |
| `$value` | `mixed` | Valeur à valider (doit être un tableau) |
| `$fail` | `Closure` | Fonction de rappel pour signaler l'échec |

**Retourne :** `void`

**Description :** Valide que la valeur est un tableau de champs et que chaque champ existe dans la liste des champs indexables du modèle.

**Exceptions :** Aucune exception directe. Les erreurs sont signalées via le `Closure $fail`.

**Exemple :**
```php
$rule = new SearchableFieldsRule(User::class);
$rule->validate('fields', ['name', 'email'], function ($message) {
    echo $message;
});
```

## Cas d'utilisation

### Cas 1 : Validation des champs de recherche dans une requête API

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use AndyDefer\LaravelIndexer\Rules\SearchableFieldsRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class SearchUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2'],
            'fields' => ['sometimes', 'array', new SearchableFieldsRule(User::class)],
            'fields.*' => ['string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function getSearchFields(): array
    {
        return $this->input('fields', ['name', 'email']);
    }
}
```

### Cas 2 : Validation dynamique avec différents modèles

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AndyDefer\LaravelIndexer\Rules\SearchableFieldsRule;
use App\Models\Drug;
use App\Models\Hospital;
use Illuminate\Http\Request;

final class SearchController
{
    public function __invoke(Request $request): array
    {
        $modelClass = $request->input('type');

        $validator = validator(
            $request->all(),
            [
                'type' => ['required', 'string', 'in:user,drug,hospital'],
                'fields' => ['sometimes', 'array', new SearchableFieldsRule($modelClass)],
                'fields.*' => ['string'],
            ]
        );

        if ($validator->fails()) {
            return ['errors' => $validator->errors()];
        }

        // Recherche avec les champs validés...
        return [];
    }
}
```

### Cas 3 : Validation en combinaison avec d'autres règles

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Rules\SearchableFieldsRule;
use App\Models\Product;

$validator = validator(
    ['fields' => ['name', 'description', 'invalid_field']],
    [
        'fields' => [
            'required',
            'array',
            'min:1',
            'max:5',
            new SearchableFieldsRule(Product::class),
        ],
        'fields.*' => ['string', 'min:2', 'max:50'],
    ]
);

if ($validator->fails()) {
    // Erreur : "Invalid field(s): invalid_field. Allowed fields: name, description, reference"
}
```

## Gestion des erreurs

| Situation | Message d'erreur |
|-----------|------------------|
| La valeur n'est pas un tableau | `The :attribute must be an array.` |
| La classe modèle n'existe pas | `The specified model class does not exist.` |
| La classe modèle n'implémente pas `Indexable` | `The specified model class must implement Indexable.` |
| Aucun champ indexable trouvé | `No searchable fields found for this model.` |
| Champs invalides présents | `Invalid field(s): {invalid_list}. Allowed fields: {allowed_list}` |

## Intégration

### Dépendances

- `IndexableFieldHelper` - Récupère la liste des champs indexables
- `Indexable` - Interface que le modèle doit implémenter

### Utilisation dans Laravel

```php
// Dans un Form Request
use AndyDefer\LaravelIndexer\Rules\SearchableFieldsRule;

public function rules(): array
{
    return [
        'fields' => ['array', new SearchableFieldsRule(User::class)],
        'fields.*' => ['string'],
    ];
}

// Dans un validateur
$validator = Validator::make($data, [
    'fields' => ['array', new SearchableFieldsRule($modelClass)],
]);
```

## Performance

- **Complexité :** O(n) où n est le nombre de champs dans le tableau `$value`
- **Découverte des champs :** L'analyse AST est effectuée à chaque validation
- **Cache :** Aucun cache interne. La découverte des champs est exécutée à chaque appel

### Optimisation recommandée

Pour les applications avec de nombreux appels de validation, envisagez de mettre en cache les champs indexables :

```php
$allowedFields = Cache::remember(
    "indexable_fields_{$this->modelClass}",
    3600,
    fn() => IndexableFieldHelper::getSearchableFields($this->modelClass)
);
```

## Compatibilité

| Version | Support |
|---------|---------|
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use AndyDefer\LaravelIndexer\Rules\SearchableFieldsRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class SearchUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'fields' => [
                'sometimes',
                'array',
                'min:1',
                new SearchableFieldsRule(User::class),
            ],
            'fields.*' => ['string', 'distinct'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }

    public function getQuery(): string
    {
        return $this->validated('q');
    }

    public function getFields(): array
    {
        return $this->validated('fields', ['name', 'email']);
    }

    public function getLimit(): int
    {
        return $this->validated('limit', 20);
    }

    public function getSort(): string
    {
        return $this->validated('sort', 'asc');
    }
}

// Utilisation dans le contrôleur
public function search(SearchUsersRequest $request): JsonResponse
{
    $results = $this->searchService->search(
        $request->getQuery(),
        $request->getFields(),
        $request->getLimit(),
        $request->getSort()
    );

    return response()->json($results);
}
```

## Voir aussi

- `IndexableFieldHelper` - Helper pour la découverte des champs indexables
- `Indexable` - Interface que les modèles indexables doivent implémenter
- `IndexableFieldDiscoveryService` - Service d'analyse AST
- [Documentation Laravel sur les règles de validation personnalisées](https://laravel.com/docs/validation#custom-validation-rules)