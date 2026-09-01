# IndexableFieldDiscoveryService - Référence Technique

## Description

Service de découverte des champs indexables depuis les modèles Eloquent utilisant l'analyse AST (Abstract Syntax Tree).

## Hiérarchie / Implémentations

```
IndexableFieldDiscoveryServiceInterface
    └── IndexableFieldDiscoveryService
```

## Rôle principal

Analyse le code source des modèles Eloquent pour extraire les clés de champs de leur méthode `getIndexableData()` sans instancier les modèles. Permet la découverte automatique des champs recherchables pour la validation et l'indexation.

## API / Méthodes publiques

### `__construct(Parser $parser)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$parser` | `PhpParser\Parser` | Analyseur AST PHP |

**Description :** Constructeur qui reçoit le parser AST.

**Exemple :**
```php
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$service = new IndexableFieldDiscoveryService($parser);
```

---

### `discoverFields(string $modelClass): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle à analyser |

**Retourne :** `array<string>` - Liste des champs indexables découverts

**Description :** Analyse un modèle et retourne tous les champs indexables définis dans sa méthode `getIndexableData()`.

**Exceptions :** Aucune exception. Toutes les erreurs sont silencieusement ignorées et retournent un tableau vide.

**Exemple :**
```php
$fields = $service->discoverFields(User::class);
// ['name', 'email', 'slug', 'profile.bio', 'profile.social.twitter']
```

---

### `discoverFieldsForMany(array $modelClasses): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClasses` | `array<class-string>` | Liste des FQCN des modèles |

**Retourne :** `array<string, array<string>>` - Tableau associatif classe → champs

**Description :** Analyse plusieurs modèles et retourne leurs champs indexables respectifs.

**Exemple :**
```php
$result = $service->discoverFieldsForMany([
    User::class,
    Product::class,
    Order::class,
]);
// [
//   'App\Models\User' => ['name', 'email'],
//   'App\Models\Product' => ['name', 'description'],
//   'App\Models\Order' => ['reference', 'customer_name']
// ]
```

---

### `discoverFieldsInDirectory(string $directory, string $namespace): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Chemin du répertoire contenant les modèles |
| `$namespace` | `string` | Namespace de base des modèles |

**Retourne :** `array<string, array<string>>` - Tableau associatif classe → champs

**Description :** Scanne un répertoire, charge toutes les classes PHP et découvre leurs champs indexables.

**Exemple :**
```php
$result = $service->discoverFieldsInDirectory(
    '/app/Models',
    'App\Models'
);
// [
//   'App\Models\User' => ['name', 'email'],
//   'App\Models\Product' => ['name', 'description']
// ]
```

## Cas d'utilisation

### Cas 1 : Découverte des champs pour la validation

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Services\IndexableFieldDiscoveryService;

final class SearchValidator
{
    public function __construct(
        private readonly IndexableFieldDiscoveryService $discoveryService,
    ) {}

    public function validateFields(string $modelClass, array $fields): bool
    {
        $allowedFields = $this->discoveryService->discoverFields($modelClass);
        return empty(array_diff($fields, $allowedFields));
    }
}

// Utilisation
$validator = new SearchValidator($discoveryService);
$isValid = $validator->validateFields(User::class, ['name', 'email']);
```

### Cas 2 : Génération de règles de validation dynamiques

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Services\IndexableFieldDiscoveryService;

final class SearchRequestRuleGenerator
{
    public function __construct(
        private readonly IndexableFieldDiscoveryService $discoveryService,
    ) {}

    public function generateRules(string $modelClass): array
    {
        $fields = $this->discoveryService->discoverFields($modelClass);

        return [
            'q' => ['required', 'string', 'min:2'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['string', 'in:' . implode(',', $fields)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

// Utilisation
$rules = $requestRuleGenerator->generateRules(User::class);
// [
//   'q' => ['required', 'string', 'min:2'],
//   'fields' => ['sometimes', 'array'],
//   'fields.*' => ['string', 'in:name,email,slug,profile.bio'],
//   'limit' => ['sometimes', 'integer', 'min:1', 'max:100']
// ]
```

### Cas 3 : Découverte en masse pour l'indexation

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Services\IndexableFieldDiscoveryService;

final class IndexerOrchestrator
{
    public function __construct(
        private readonly IndexableFieldDiscoveryService $discoveryService,
    ) {}

    public function discoverAllIndexableFields(): array
    {
        return $this->discoveryService->discoverFieldsInDirectory(
            __DIR__ . '/../Models',
            'App\Models'
        );
    }

    public function generateIndexationPlan(): array
    {
        $allFields = $this->discoverAllIndexableFields();
        $plan = [];

        foreach ($allFields as $model => $fields) {
            $plan[$model] = [
                'searchable_fields' => $fields,
                'default_fields' => array_slice($fields, 0, 3),
                'total_fields' => count($fields),
            ];
        }

        return $plan;
    }
}
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Classe inexistante | Retourne `[]` |
| Fichier introuvable | Retourne `[]` |
| Contenu du fichier invalide | Retourne `[]` |
| Erreur de parsing AST | Retourne `[]` |
| Toute autre exception | Retourne `[]` |

**Note :** Toutes les erreurs sont silencieusement ignorées pour permettre une exécution robuste en environnement de production.

## Intégration

### Dépendances

- `PhpParser\Parser` - Analyse AST
- `IndexableFieldDiscoveryVisitor` - Visiteur AST personnalisé
- `IndexableFieldDiscoveryServiceInterface` - Interface implémentée

### Enregistrement dans le conteneur Laravel

```php
// Dans le ServiceProvider
use AndyDefer\LaravelIndexer\Services\IndexableFieldDiscoveryService;
use AndyDefer\LaravelIndexer\Contracts\Services\IndexableFieldDiscoveryServiceInterface;

$this->app->singleton(IndexableFieldDiscoveryService::class, function ($app) {
    return new IndexableFieldDiscoveryService(
        parser: $app->make(\PhpParser\Parser::class)
    );
});

$this->app->bind(
    IndexableFieldDiscoveryServiceInterface::class,
    IndexableFieldDiscoveryService::class
);
```

### Utilisation avec le helper

```php
use AndyDefer\LaravelIndexer\Helpers\IndexableFieldHelper;

// Le helper utilise automatiquement le service
$fields = IndexableFieldHelper::getSearchableFields(User::class);
```

## Performance

- **Complexité :** O(n) où n est la taille du fichier source
- **Mémoire :** Le fichier est chargé en mémoire pour l'analyse AST
- **Cache :** Aucun cache interne. Chaque appel relit le fichier et le parse
- **Optimisation recommandée :** Mettre en cache les résultats pour les appels fréquents

```php
// Optimisation avec cache
$fields = Cache::remember(
    "indexable_fields_{$modelClass}",
    3600,
    fn() => $this->discoveryService->discoverFields($modelClass)
);
```

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |
| PHP 8.4+ | ✅ Complet |
| PHP 8.5+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Services\IndexableFieldDiscoveryService;
use PhpParser\ParserFactory;

// 1. Créer le service
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$service = new IndexableFieldDiscoveryService($parser);

// 2. Découverte d'un modèle unique
$userFields = $service->discoverFields(\App\Models\User::class);
echo "User fields: " . implode(', ', $userFields) . "\n";
// User fields: name, email, slug, profile.bio, profile.social.twitter

// 3. Découverte de plusieurs modèles
$models = [
    \App\Models\User::class,
    \App\Models\Product::class,
    \App\Models\Order::class,
];

$allFields = $service->discoverFieldsForMany($models);
foreach ($allFields as $model => $fields) {
    echo sprintf("%s: %d fields\n", $model, count($fields));
}

// 4. Découverte d'un répertoire entier
$directoryFields = $service->discoverFieldsInDirectory(
    __DIR__ . '/../Models',
    'App\Models'
);

foreach ($directoryFields as $model => $fields) {
    echo sprintf(
        "%s: %s\n",
        $model,
        implode(', ', array_slice($fields, 0, 3))
    );
}

// 5. Génération de règles de validation
function generateValidationRules(string $modelClass, IndexableFieldDiscoveryService $service): array
{
    $fields = $service->discoverFields($modelClass);

    return [
        'search' => ['required', 'string', 'min:2'],
        'fields' => ['sometimes', 'array'],
        'fields.*' => ['string', 'in:' . implode(',', $fields)],
    ];
}

$rules = generateValidationRules(\App\Models\User::class, $service);
print_r($rules);
```

## Voir aussi

- `IndexableFieldDiscoveryVisitor` - Visiteur AST utilisé en interne
- `IndexableFieldHelper` - Helper public pour la découverte des champs
- `SearchableFieldsRule` - Règle de validation utilisant ce service
- `IndexableFieldDiscoveryServiceInterface` - Interface du service
- [Documentation PHP Parser](https://github.com/nikic/PHP-Parser) - Bibliothèque d'analyse AST