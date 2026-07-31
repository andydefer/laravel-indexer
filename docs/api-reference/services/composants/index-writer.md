# IndexWriter - Référence Technique

## Description

Service d'écriture pour l'indexation des tokens. Gère la génération de tokens, le buffering et l'insertion par lots pour une indexation efficace du contenu des documents.

## Hiérarchie / Implémentations

```
IndexWriter (classe finale)
    ├── Dépend de IndexedDocumentRepository
    ├── Dépend de IndexedTokenRepository
    ├── Dépend de TextNormalizerInterface
    ├── Dépend de NGramGeneratorInterface
    └── Dépend de IndexerConfigInterface
```

## Rôle principal

Ce service est le moteur d'écriture du système d'indexation. Il orchestre :

- La création des documents indexés
- L'extraction des tokens depuis les données
- La génération de n-grams lexicaux et métaphones
- Le buffering des tokens pour optimisation des performances
- L'insertion en masse en base de données

### Responsabilités

1. **Indexation unitaire** : `index()`
2. **Indexation par lots** : `indexMany()`
3. **Création de documents** : `createDocument()`
4. **Extraction de tokens** : `extractAndBufferTokens()`
5. **Gestion des buffers** : `addToBuffer()`, `flushTokens()`
6. **Génération de n-grams** : `processWord()`

## Détails

[Voir la classe IndexedDocumentRecord](https://github.com/andydefer/laravel-indexer/blob/main/src/Records/IndexedDocumentRecord.php)

[Voir l'interface NGramGeneratorInterface](https://github.com/andydefer/php-services/blob/main/src/Contracts/Services/NGramGeneratorInterface.php)

## API / Méthodes publiques

### `index(IndexedDocumentRecord $entity): void`

Indexe un seul enregistrement de document.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$entity` | `IndexedDocumentRecord` | Enregistrement du document à indexer |

**Retourne :** `void`

**Exemple :**
```php
$record = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|123'),
    cluster: new ClusterVO(['status' => 'active']),
    data: StrictAssociative::from(['name' => 'John Doe'])
);

$writer->index($record);
// Le document est créé et tous ses tokens sont indexés
```

---

### `indexMany(IndexableRecordCollection $records): void`

Indexe plusieurs enregistrements de documents en une seule opération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `IndexableRecordCollection` | Collection des enregistrements à indexer |

**Retourne :** `void`

**Exemple :**
```php
$records = new IndexableRecordCollection();
$records->add($record1);
$records->add($record2);
$records->add($record3);

$writer->indexMany($records);
// Tous les documents sont créés et leurs tokens indexés en une seule opération
```

---

## Architecture interne

### Buffering

L'`IndexWriter` utilise un système de buffering pour optimiser les performances :

```
Token Buffer (array<string, array>)
    └── Clé: "docId|token|field|type"
    └── Valeur: Données complètes du token

Increment Buffer (array<string, int>)
    └── Clé: "docId|token|field|type"
    └── Valeur: Nombre d'incréments à appliquer

flushTokens()
    ├── Crée les nouveaux tokens (INSERT)
    └── Met à jour les tokens existants (UPDATE frequency)
```

### Taille du buffer

- **`$bufferSize`** : 5000 tokens (défaut)
- **`$insertChunkSize`** : 1000 tokens par INSERT (défaut)

---

## Cas d'utilisation

### Cas 1 : Indexation d'un nouveau document

```php
class DocumentService
{
    public function __construct(
        private readonly IndexWriter $writer
    ) {}

    public function createAndIndex(array $data): void
    {
        $record = new IndexedDocumentRecord(
            fingerprint: new IndexableFingerprintVO('App\Models\Document|' . uniqid()),
            cluster: new ClusterVO(['type' => 'document', 'status' => 'published']),
            data: StrictAssociative::from($data)
        );
        
        $this->writer->index($record);
    }
}
```

### Cas 2 : Indexation par lots

```php
class BatchIndexer
{
    public function __construct(
        private readonly IndexWriter $writer
    ) {}

    public function indexAll(array $models): void
    {
        $records = new IndexableRecordCollection();
        
        foreach ($models as $model) {
            $records->add(
                IndexableRecordFactory::convert($model, $model->getIndexableCluster())
            );
        }
        
        $this->writer->indexMany($records);
    }
}
```

### Cas 3 : Réindexation d'un document existant

```php
class RefreshIndexService
{
    public function __construct(
        private readonly IndexWriter $writer,
        private readonly IndexDeleter $deleter
    ) {}

    public function refresh(Model&Indexable $model): void
    {
        // Supprimer l'ancien index
        $fingerprint = IndexableFingerprintVO::fromParts(
            $model->getMorphClass(),
            (string) $model->getKey()
        );
        $this->deleter->delete($fingerprint);
        
        // Créer le nouvel index
        $record = IndexableRecordFactory::convert(
            $model,
            $model->getIndexableCluster()
        );
        $this->writer->index($record);
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Échec du flush des tokens | `RuntimeException` | `Failed to flush tokens: {message}` |
| Erreur de transaction | `Exception` | Propagée depuis la base de données |

---

## Intégration

Ce service est utilisé par :

- **`IndexerService`** : Pour les opérations d'indexation
- **`GenericIndexerService`** : Pour l'indexation par lots

### Flux de données

```
IndexWriter
    │
    ├── index()
    │   ├── resetBuffers()
    │   ├── createDocument()
    │   ├── indexDocumentData()
    │   │   ├── Traitement récursif des données
    │   │   ├── extractAndBufferTokensShort()
    │   │   └── extractAndBufferTokensLong()
    │   └── flushTokens()
    │
    └── indexMany()
        ├── resetBuffers()
        ├── Pour chaque record
        │   ├── createDocument()
        │   └── indexDocumentData()
        └── flushTokens()
```

---

## Performance

| Opération | Complexité | Optimisation |
|-----------|------------|--------------|
| `index()` | O(n * m) | n = données, m = nombre de tokens |
| `indexMany()` | O(N * n * m) | N = nombre de documents |
| `flushTokens()` | O(B) | B = taille du buffer |

**Optimisations :**

- Buffer de tokens pour réduire les requêtes SQL
- Insertion par lots (`INSERT` en une seule requête)
- Incrémentation en masse (`UPDATE` groupé)
- Découpage des textes longs en chunks

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

use AndyDefer\LaravelIndexer\Services\Composants\IndexWriter;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

$writer = new IndexWriter(
    $documentRepository,
    $tokenRepository,
    $textNormalizer,
    $ngramGenerator,
    $config
);

// 1. Indexation d'un document simple
$record = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|123'),
    cluster: new ClusterVO(['status' => 'active', 'role' => 'admin']),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'bio' => 'Software developer with 10 years of experience'
    ])
);
$writer->index($record);

// 2. Indexation par lots
$records = new IndexableRecordCollection();
$records->add($record1);
$records->add($record2);
$records->add($record3);
$writer->indexMany($records);

// 3. Indexation avec données imbriquées
$record = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\Product|456'),
    cluster: new ClusterVO(['category' => 'electronics', 'status' => 'active']),
    data: StrictAssociative::from([
        'name' => 'Laptop Pro',
        'specs' => [
            'cpu' => 'Intel i7',
            'ram' => '16GB',
            'storage' => '512GB SSD'
        ],
        'tags' => ['laptop', 'computer', 'portable']
    ])
);
$writer->index($record);
// Les données imbriquées sont correctement indexées
```

---

## Voir aussi

- `IndexedDocumentRecord` - Record de document
- `IndexedDocumentRepository` - Repository des documents
- `IndexedTokenRepository` - Repository des tokens
- `NGramGeneratorInterface` - Interface de génération de n-grams
- `TextNormalizerInterface` - Interface de normalisation
- `IndexerConfigInterface` - Configuration de l'indexation