<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The path where the index files will be stored.
    |
    */
    'storage_path' => storage_path('indexer'),

    /*
    |--------------------------------------------------------------------------
    | Token Types
    |--------------------------------------------------------------------------
    |
    | The types of tokens to generate for indexing.
    |
    */
    'token_types' => [
        'ngrams' => [
            'min_size' => 3,
            'max_size' => 5,
        ],
        'metaphone' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Limit
    |--------------------------------------------------------------------------
    |
    | The default limit for search results.
    |
    */
    'default_limit' => 100,

    /*
    |--------------------------------------------------------------------------
    | Enable Cache
    |--------------------------------------------------------------------------
    |
    | Whether to cache search results.
    |
    */
    'enable_cache' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | The time-to-live for cached search results in seconds.
    |
    */
    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Batch Size
    |--------------------------------------------------------------------------
    |
    | Number of items to process per batch during indexing.
    |
    */
    'batch_size' => env('INDEXER_BATCH_SIZE', 50),

    /*
    |--------------------------------------------------------------------------
    | Model Indexables
    |--------------------------------------------------------------------------
    |
    | List of model classes with their cluster configuration.
    | Key: fully qualified class name of the model
    | Value: cluster string for indexing
    |
    */
    'model_indexables' => [
        // App\Models\User::class => 'type:user|role:doctor',
        // App\Models\Hospital::class => 'type:hospital|status:active',
    ],
];
