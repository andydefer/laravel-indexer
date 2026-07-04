
# Documentation du système d'indexation

## 1. Introduction

Le système d'indexation permet de stocker et de récupérer rapidement des données structurées via des tokens (n-grammes et metaphones) générés à partir des champs d'un `IndexableRecord`.

**Objectif :** Recherche en **O(k)** où `k` est le nombre de résultats, sans parcours linéaire sur de grands volumes.

---

## 2. Structure des données

### 2.1 IndexableRecord

```php
$record = new IndexableRecord(
    id: new IndexableEntityIdVO('App.Models.User|123'),
    cluster: new ClusterVO('model-User|tenant-company_abc|env-production'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'description' => 'Software Developer'
    ])
);
```

### 2.2 Structure de stockage UNIQUE

```
storage/indexer/
│
├── documents/
│   └── {namespace}|{id}.json
│
├── ngrams/
│   └── {premiere_lettre}/
│       └── {token}/
│           └── {namespace}/
│               ├── data/
│               │   └── {id}.json
│               ├── fields/
│               │   └── {field_name}/
│               │       └── {id}.json
│               └── cluster/
│                   └── {key}/
│                       └── {value}/
│                           └── {id}.json
│
└── metaphones/
    └── {premiere_lettre}/
        └── {token}/
            └── {namespace}/
                ├── data/
                │   └── {id}.json
                ├── fields/
                │   └── {field_name}/
                │       └── {id}.json
                └── cluster/
                    └── {key}/
                        └── {value}/
                            └── {id}.json
```

### 2.3 Contenu des fichiers

**data/{id}.json**
```json
{
    "cluster": {"model":"User","tenant":"company_abc","env":"production"},
    "fields": ["name", "description"]
}
```

**fields/{field_name}/{id}.json**
```json
{"ref": "../data/{id}.json"}
```

**cluster/{key}/{value}/{id}.json**
```json
{"ref": "../../../data/{id}.json"}
```

---

## 3. Processus d'indexation

### 3.1 Étapes

```
1. Réception d'un IndexableRecord
    ↓
2. Sauvegarde du document
   → documents/{namespace}|{id}.json
    ↓
3. Pour chaque champ de data
    ↓
4. Normalisation du texte
    ↓
5. Génération des tokens :
    ├── N-grammes (taille 2 à 4)
    └── Metaphone
    ↓
6. Pour chaque token :
    ├── data/{id}.json → cluster + fields
    ├── fields/{field_name}/{id}.json → ref vers data/{id}.json
    └── cluster/{key}/{value}/{id}.json → ref vers data/{id}.json
```

### 3.2 Exemple

**Donnée :**
```php
$record = new IndexableRecord(
    id: new IndexableEntityIdVO('App.Models.User|123'),
    cluster: new ClusterVO('model-User|tenant-company_abc|env-production'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'description' => 'Software Developer'
    ])
);
```

**Résultat :**

```
storage/indexer/
│
├── documents/
│   └── App.Models.User|123.json
│
├── ngrams/
│   ├── j/
│   │   ├── jo/
│   │   │   └── App.Models.User/
│   │   │       ├── data/123.json
│   │   │       ├── fields/name/123.json
│   │   │       └── cluster/model/User/123.json
│   │   │
│   │   ├── john/
│   │   │   └── App.Models.User/
│   │   │       ├── data/123.json
│   │   │       ├── fields/name/123.json
│   │   │       └── cluster/model/User/123.json
│   │   │
│   │   └── jane/
│   │       └── App.Models.User/
│   │           ├── data/456.json
│   │           ├── fields/name/456.json
│   │           └── cluster/model/User/456.json
│   │
│   └── s/
│       ├── so/
│       │   └── App.Models.User/
│       │       ├── data/123.json
│       │       ├── fields/description/123.json
│       │       └── cluster/model/User/123.json
│       │
│       └── soft/
│           └── App.Models.User/
│               ├── data/123.json
│               ├── fields/description/123.json
│               └── cluster/model/User/123.json
│
└── metaphones/
    ├── J/
    │   └── JN/
    │       └── App.Models.User/
    │           ├── data/123.json
    │           ├── data/456.json
    │           ├── fields/name/123.json
    │           ├── fields/name/456.json
    │           └── cluster/model/User/123.json
    │           └── cluster/model/User/456.json
    │
    └── S/
        └── SFTWR/
            └── App.Models.User/
                ├── data/123.json
                ├── fields/description/123.json
                └── cluster/model/User/123.json
```

---

## 4. Processus de recherche

### 4.1 Recherche simple

```
Recherche : "john"

1. Chemin : ngrams/j/john/App.Models.User/data/
2. Lecture du dossier → [123.json, 456.json]
3. Retourne les IDs → ["123", "456"]

Complexité : O(k) où k = nombre de résultats
```

### 4.2 Recherche avec filtre champ

```
Recherche : "john" dans "name"

1. Chemin : ngrams/j/john/App.Models.User/fields/name/
2. Lecture du dossier → [123.json, 456.json]
3. Retourne les IDs → ["123", "456"]

Complexité : O(k) où k = nombre de résultats
```

### 4.3 Recherche avec filtre cluster

```
Recherche : "john" ET cluster "model-User"

1. ngrams/j/john/App.Models.User/fields/name/ → [123, 456]
2. ngrams/j/john/App.Models.User/cluster/model/User/ → [123, 456]
3. Intersection → [123, 456]

Complexité : O(k1 + k2)
```

### 4.4 Recherche multiple

```
Recherche : "john" AND "soft" AND cluster "model-User"

1. "john" → [123, 456]
2. "soft" → [123]
3. cluster "model-User" → [123, 456]
4. Intersection → [123]

Complexité : O(k1 + k2 + k3)
```

---

## 5. Avantages de la structure

| Avantage | Explication |
|----------|-------------|
| **O(k)** | Lecture uniquement des résultats, pas de parcours global |
| **Accès direct** | Chemin = token + filtre → O(1) |
| **Filtrage intégré** | Champs et clusters organisés en dossiers |
| **Pas de duplication** | Les références pointent vers `data/` |
| **Namespace isolé** | Chaque namespace a ses propres dossiers |
| **Mise à jour simple** | Ajout/suppression d'un fichier `{id}.json` |

---

## 6. Schéma récapitulatif

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                               INDEXATION                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  IndexableRecord → Normalisation → Tokenisation → Stockage                  │
│                                                                             │
│  Pour chaque token :                                                        │
│  └── {type}/{premiere_lettre}/{token}/{namespace}/                         │
│      ├── data/{id}.json → {cluster, fields}                                │
│      ├── fields/{field}/{id}.json → ref vers data/{id}.json               │
│      └── cluster/{key}/{value}/{id}.json → ref vers data/{id}.json        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
                                       ↓
┌─────────────────────────────────────────────────────────────────────────────┐
│                               RECHERCHE                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Token → chemin direct → O(1)                                           │
│  2. Lecture du dossier → O(k)                                              │
│  3. Retour des IDs → O(1)                                                  │
│                                                                             │
│  Avec filtre champ :                                                        │
│  1. Token + champ → chemin direct → O(1)                                   │
│  2. Lecture du dossier → O(k)                                              │
│  3. Retour des IDs → O(1)                                                  │
│                                                                             │
│  Avec filtre cluster :                                                      │
│  1. Token + cluster → chemin direct → O(1)                                 │
│  2. Lecture du dossier → O(k)                                              │
│  3. Retour des IDs → O(1)                                                  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```