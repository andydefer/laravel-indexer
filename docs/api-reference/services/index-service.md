# IndexerService - Référence Technique

## Description

Service principal orchestrant toutes les opérations d'indexation. Agit comme une façade déléguant les opérations aux composants spécialisés : `IndexWriter` pour la création, `IndexDeleter` pour la suppression, et `IndexSearcher` pour la recherche.

## Hiérarchie / Implémentations

```
IndexerService (classe finale)
    ├── Implémente IndexerInterface
    ├── Délègue à IndexWriter
    ├── Délègue à IndexDeleter
    └── Délègue à IndexSearcher
```

## Rôle principal

Ce service est le point d'entrée unique pour toutes les opérations d'indexation de bas niveau. Il agit comme un **façade** qui orchestre :

- La création et l'indexation des documents
- La suppression unitaire et par lots
- Le rafraîchissement des documents
- La recherche et la vérification d'existence

### Responsabilités

1. **Indexation** : `index()`, `indexMany()`
2. **Rafraîchissement** : `refresh()`, `refreshMany()`
3. **Suppression** : `delete()`, `deleteMany()`, `clear()`
4. **Recherche** : `search()`, `exists()`

## Détails

[Voir la classe IndexerInterface](https://github.com/andydefer/laravel-indexer/blob/main/src/Contracts/IndexerInterface.php)

[Voir la classe IndexWriter](https://github.com/andydefer/laravel-indexer/blob/main/src/Services/Composants/IndexWriter.php)

[Voir la classe IndexDeleter](https://github.com/andydefer/laravel-indexer/blob/main/src/Services/Composants/IndexDeleter.php)

[Voir la classe IndexSearcher](https://github.com/andydefer/laravel-indexer/blob/main/src/Services/Composants/IndexSearcher.php)

## API / Méthodes publiques

### `index(IndexedDocumentRecord $entity): void`

Indexe un seul document.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$entity` | `IndexedDocumentRecord` | Document à indexer |

**Retourne :** `void`

**Exemple :**
```php
$record = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|123'),
    cluster: new ClusterVO(['status' => 'active']),
    data: StrictAssociative::from(['name' => 'John Doe'])
);

$service->index($record);
```

---

### `indexMany(IndexableRecordCollection $records): void`

Indexe plusieurs documents en une seule opération.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `IndexableRecordCollection` | Collection des documents à indexer |

**Retourne :** `void`

**Exemple :**
```php
$records = new IndexableRecordCollection();
$records->add($record1);
$records->add($record2);
$records->add($record3);

$service->indexMany($records);
```

---

### `delete(IndexableFingerprintVO $fingerprint): void`

Supprime un document par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint du document à supprimer |

**Retourne :** `void`

**Exemple :**
```php
$fingerprint = new IndexableFingerprintVO('App\Models\User|123');
$service->delete($fingerprint);
```

---

### `deleteMany(IndexableFingerPrintVOCollection $fingerPrints): void`

Supprime plusieurs documents par leurs fingerprints.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrints` | `IndexableFingerPrintVOCollection` | Collection des fingerprints à supprimer |

**Retourne :** `void`

---

### `clear(): void`

Supprime tous les documents de l'index (opération destructive).

**Retourne :** `void`

**⚠️ Attention :** Cette opération est irréversible.

**Exemple :**
```php
$service->clear();
// L'index est entièrement vidé
```

---

### `exists(IndexableFingerprintVO $fingerprint): bool`

Vérifie si un document existe par son fingerprint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `IndexableFingerprintVO` | Fingerprint à vérifier |

**Retourne :** `bool` - `true` si le document existe

**Exemple :**
```php
$exists = $service->exists(
    new IndexableFingerprintVO('App\Models\User|123')
);
```

---

### `search(SearchQueryRecord $query): IndexableSearchResultCollection`

Effectue une recherche avec la requête donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `SearchQueryRecord` | Configuration de la requête |

**Retourne :** `IndexableSearchResultCollection` - Résultats de la recherche

**Exemple :**
```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,description'),
    limit: 10
);

$results = $service->search($query);
foreach ($results as $result) {
    echo $result->item->fingerprint->getValue();
}
```

---

### `refresh(IndexedDocumentRecord $entity): void`

Rafraîchit un document (supprime puis recrée).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$entity` | `IndexedDocumentRecord` | Document à rafraîchir |

**Retourne :** `void`

**Exemple :**
```php
$record = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|123'),
    cluster: new ClusterVO(['status' => 'active']),
    data: StrictAssociative::from(['name' => 'John Updated'])
);

$service->refresh($record);
// L'ancien document est supprimé, le nouveau est créé
```

---

### `refreshMany(IndexableRecordCollection $records): void`

Rafraîchit plusieurs documents (supprime puis recrée).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `IndexableRecordCollection` | Collection des documents à rafraîchir |

**Retourne :** `void`

---

## Cas d'utilisation

### Cas 1 : Indexation d'un nouveau document

```php
class DocumentService
{
    public function __construct(
        private readonly IndexerService $indexer
    ) {}

    public function create(array $data): Document
    {
        $document = Document::create($data);
        
        $record = new IndexedDocumentRecord(
            fingerprint: IndexableFingerprintVO::fromParts(
                $document->getMorphClass(),
                (string) $document->getKey()
            ),
            cluster: $document->getIndexableCluster(),
            data: $document->getIndexableData()
        );
        
        $this->indexer->index($record);
        return $document;
    }
}
```

### Cas 2 : Recherche full-text

```php
class SearchController
{
    public function __construct(
        private readonly IndexerService $indexer
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = new SearchQueryRecord(
            query: new SearchQueryVO($request->get('q')),
            limit: $request->get('limit', 20)
        );
        
        $results = $this->indexer->search($query);
        
        return response()->json([
            'results' => $results->getItems()->toArray(),
            'total' => $results->count()
        ]);
    }
}
```

### Cas 3 : Rafraîchissement après mise à jour

```php
class DocumentService
{
    public function update(int $id, array $data): Document
    {
        $document = Document::findOrFail($id);
        $document->update($data);
        
        $record = new IndexedDocumentRecord(
            fingerprint: IndexableFingerprintVO::fromParts(
                $document->getMorphClass(),
                (string) $document->getKey()
            ),
            cluster: $document->getIndexableCluster(),
            data: $document->getIndexableData()
        );
        
        $this->indexer->refresh($record);
        return $document;
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Échec d'insertion des tokens | `RuntimeException` | `Failed to flush tokens: {message}` |
| Erreur de suppression | `Throwable` | Dépend de la couche base de données |

---

## Intégration

Ce service est le cœur du système d'indexation. Il est utilisé par :

- **`GenericIndexerService`** : Pour l'indexation de haut niveau
- **`IndexableRecordFactory`** : Pour la conversion des modèles
- **`GenericIndexModelsDirective`** : Pour les commandes CLI

### Flux de données

```
IndexerService
    │
    ├── index() → IndexWriter::index()
    ├── indexMany() → IndexWriter::indexMany()
    ├── delete() → IndexDeleter::delete()
    ├── deleteMany() → IndexDeleter::deleteMany()
    ├── clear() → IndexDeleter::clear()
    ├── exists() → IndexSearcher::exists()
    ├── search() → IndexSearcher::search()
    ├── refresh() → IndexDeleter::delete() + IndexWriter::index()
    └── refreshMany() → IndexDeleter::deleteMany() + IndexWriter::indexMany()
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `index()` | O(m) | m = nombre de tokens |
| `indexMany()` | O(n * m) | n = nombre de documents |
| `delete()` | O(1) | Suppression par fingerprint |
| `deleteMany()` | O(n) | n = nombre de fingerprints |
| `clear()` | O(n) | Supprime tous les enregistrements |
| `search()` | Variable | Dépend de la complexité de la requête |
| `refresh()` | O(1) + O(m) | Suppression + indexation |
| `refreshMany()` | O(n) + O(n * m) | Suppression + indexation |

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

use AndyDefer\LaravelIndexer\Services\IndexerService;
use AndyDefer\LaravelIndexer\Records\IndexedDocumentRecord;
use AndyDefer\LaravelIndexer\Records\SearchQueryRecord;
use AndyDefer\LaravelIndexer\ValueObjects\IndexableFingerprintVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

$service = new IndexerService($writer, $deleter, $searcher);

// 1. Indexer un document
$record = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|123'),
    cluster: new ClusterVO(['status' => 'active']),
    data: StrictAssociative::from(['name' => 'John Doe'])
);
$service->index($record);

// 2. Vérifier l'existence
$exists = $service->exists(
    new IndexableFingerprintVO('App\Models\User|123')
);
echo "Document existe: " . ($exists ? 'Oui' : 'Non') . "\n";

// 3. Rechercher
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    limit: 10
);
$results = $service->search($query);
echo "Résultats: " . $results->count() . "\n";

// 4. Rafraîchir un document
$updatedRecord = new IndexedDocumentRecord(
    fingerprint: new IndexableFingerprintVO('App\Models\User|123'),
    cluster: new ClusterVO(['status' => 'inactive']),
    data: StrictAssociative::from(['name' => 'John Smith'])
);
$service->refresh($updatedRecord);

// 5. Supprimer un document
$service->delete(new IndexableFingerprintVO('App\Models\User|123'));
```

---

## Voir aussi

- `IndexerInterface` - Interface du service
- `IndexWriter` - Composant d'écriture
- `IndexDeleter` - Composant de suppression
- `IndexSearcher` - Composant de recherche
- `IndexedDocumentRecord` - Record de document
- `SearchQueryRecord` - Record de requête