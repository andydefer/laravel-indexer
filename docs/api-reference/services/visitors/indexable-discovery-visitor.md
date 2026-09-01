# IndexableFieldDiscoveryVisitor - Référence Technique

## Description

Visiteur AST (Abstract Syntax Tree) qui extrait les clés de champs indexables depuis la méthode `getIndexableData()` des modèles Eloquent.

## Hiérarchie / Implémentations

```
PhpParser\NodeVisitorAbstract
    └── IndexableFieldDiscoveryVisitor
```

## Rôle principal

Parcourt l'arbre syntaxique d'un fichier PHP pour localiser la méthode `getIndexableData()` et extraire toutes les clés des tableaux passés à `StrictAssociative::from()`, `new StrictAssociative()` ou `$this->from()`. Permet de découvrir les champs indexables d'un modèle sans l'instancier.

## API / Méthodes publiques

### `enterNode(Node $node): ?int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$node` | `PhpParser\Node` | Nœud AST à visiter |

**Retourne :** `?int` - `null` pour continuer la traversée

**Description :** Méthode appelée par le traverseur AST lorsqu'un nœud est rencontré. Identifie les classes, namespaces et la méthode `getIndexableData()`.

**Exemple :**
```php
$visitor = new IndexableFieldDiscoveryVisitor();
$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);
```

---

### `getFields(): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| - | - | - |

**Retourne :** `array<string>` - Liste des clés de champs découvertes, uniques

**Description :** Retourne la liste des champs indexables extraits lors de la traversée.

**Exemple :**
```php
$visitor = new IndexableFieldDiscoveryVisitor();
$traverser->traverse($ast);
$fields = $visitor->getFields();
// ['name', 'email', 'profile.bio', 'profile.social.twitter']
```

---

### `getFullyQualifiedClassName(): ?string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| - | - | - |

**Retourne :** `?string` - FQCN du modèle ou `null` si non trouvé

**Description :** Retourne le nom complet de la classe (namespace + nom) du modèle analysé.

**Exemple :**
```php
$fqcn = $visitor->getFullyQualifiedClassName();
// 'App\Models\User'
```

## Cas d'utilisation

### Cas 1 : Découverte des champs d'un modèle utilisateur

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Services\Visitors\IndexableFieldDiscoveryVisitor;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

$code = <<<'PHP'
class User implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'profile' => [
                'bio' => $this->bio,
                'social' => [
                    'twitter' => $this->twitter,
                ],
            ],
        ]);
    }
}
PHP;

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$ast = $parser->parse($code);

$visitor = new IndexableFieldDiscoveryVisitor();
$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);

$fields = $visitor->getFields();
// ['name', 'email', 'profile', 'profile.bio', 'profile.social', 'profile.social.twitter']
```

### Cas 2 : Découverte avec variable intermédiaire

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelIndexer\Services\Visitors\IndexableFieldDiscoveryVisitor;

$code = <<<'PHP'
class Drug implements Indexable
{
    public function getIndexableData(): StrictAssociative
    {
        $data = [
            'name' => $this->name,
            'generic_name' => $this->generic_name,
            'slug' => $this->slug,
        ];

        return StrictAssociative::from($data);
    }
}
PHP;

$visitor = new IndexableFieldDiscoveryVisitor();
// Traversée...
$fields = $visitor->getFields();
// ['name', 'generic_name', 'slug']
```

### Cas 3 : Support des structures imbriquées profondes

```php
$code = <<<'PHP'
public function getIndexableData(): StrictAssociative
{
    return StrictAssociative::from([
        'profile' => [
            'personal' => [
                'bio' => $this->bio,
                'social' => [
                    'twitter' => $this->twitter,
                    'github' => $this->github,
                    'linkedin' => [
                        'url' => $this->linkedin_url,
                        'handle' => $this->linkedin_handle,
                    ],
                ],
            ],
        ],
    ]);
}
PHP;

$fields = $visitor->getFields();
// [
//   'profile',
//   'profile.personal',
//   'profile.personal.bio',
//   'profile.personal.social',
//   'profile.personal.social.twitter',
//   'profile.personal.social.github',
//   'profile.personal.social.linkedin',
//   'profile.personal.social.linkedin.url',
//   'profile.personal.social.linkedin.handle'
// ]
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Méthode `getIndexableData()` absente | `$visitor->getFields()` retourne `[]` |
| Pas de retour dans `getIndexableData()` | `$visitor->getFields()` retourne `[]` |
| Syntaxe PHP invalide | Le parser lève une exception `PhpParser\Error` |
| Fichier sans namespace | `$visitor->getFullyQualifiedClassName()` retourne `null` |

## Intégration

### Dépendances

- `php-parser` (nikic/php-parser) - Analyse AST
- `NodeVisitorAbstract` - Classe de base des visiteurs

### Utilisation avec le service de découverte

```php
use AndyDefer\LaravelIndexer\Services\Visitors\IndexableFieldDiscoveryVisitor;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$content = file_get_contents('/path/to/User.php');
$ast = $parser->parse($content);

$visitor = new IndexableFieldDiscoveryVisitor();
$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);

$fields = $visitor->getFields();
$className = $visitor->getFullyQualifiedClassName();
```

## Performance

- **Complexité :** O(n) où n est le nombre de nœuds AST du fichier
- **Mémoire :** Le visitor ne stocke que les noms de classes et les champs découverts
- **Optimisation :** La traversée s'arrête après avoir traité `getIndexableData()`

### Formats supportés

| Construction | Supporté |
|--------------|----------|
| `StrictAssociative::from([...])` | ✅ |
| `new StrictAssociative([...])` | ✅ |
| `StrictAssociative::from($variable)` | ✅ |
| `$this->from([...])` | ✅ |
| Tableaux imbriqués | ✅ |
| Opérateurs ternaires | ✅ |
| Variables assignées | ✅ |

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

use AndyDefer\LaravelIndexer\Services\Visitors\IndexableFieldDiscoveryVisitor;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

// 1. Préparer le code source
$code = <<<'PHP'
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;

final class User extends Model implements Indexable
{
    public function shouldBeIndexed(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    public function getIndexableData(): StrictAssociative
    {
        $profile = [
            'bio' => $this->bio,
            'social' => [
                'twitter' => $this->twitter_handle,
                'github' => $this->github_username,
            ],
        ];

        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'slug' => $this->slug,
            'profile' => $profile,
        ]);
    }

    public function getMorphClass()
    {
        return self::class;
    }

    public function getIndexableCluster(): ClusterVO
    {
        return ClusterVO::from(['type' => 'user']);
    }
}
PHP;

// 2. Analyser le code
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$ast = $parser->parse($code);

$visitor = new IndexableFieldDiscoveryVisitor();
$traverser = new NodeTraverser();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);

// 3. Récupérer les résultats
$fields = $visitor->getFields();
$className = $visitor->getFullyQualifiedClassName();

echo "Modèle : {$className}\n";
echo "Champs : " . implode(', ', $fields) . "\n";

// Sortie :
// Modèle : App\Models\User
// Champs : name, email, slug, profile, profile.bio, profile.social, profile.social.twitter, profile.social.github
```

## Voir aussi

- `IndexableFieldDiscoveryService` - Service utilisant ce visitor
- `IndexableFieldHelper` - Helper pour la découverte des champs
- [Documentation PHP Parser](https://github.com/nikic/PHP-Parser) - Bibliothèque d'analyse AST
- `SearchableFieldsRule` - Règle de validation utilisant les champs découverts