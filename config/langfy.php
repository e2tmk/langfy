<?php

declare(strict_types = 1);

return [
    /*
    |--------------------------------------------------------------------------
    | From Language
    |--------------------------------------------------------------------------
    |
    | The source language for translations. This is the language that your
    | application strings are written in.
    |
    */
    'from_language' => env('LANGFY_FROM_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | To Languages
    |--------------------------------------------------------------------------
    |
    | The target languages to translate to. These are the languages that
    | the translation service will create translations for.
    |
    */
    'to_language' => env('LANGFY_TO_LANGUAGES', [
        'es_ES', // Spanish (Spain)
        'pt_BR', // Portuguese (Brazil)
    ]),

    /*
    |--------------------------------------------------------------------------
    | Finder Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the string finder service.
    |
    */
    'finder' => [
        'ignore_paths' => [
            'packages',
            'vendor',
            'node_modules',
            'storage',
            'bootstrap/cache',
        ],

        'ignore_extensions' => [
            'json',
            'md',
            'txt',
            'log',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Translation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI-powered translation services.
    | This will be implemented in future versions.
    |
    */
    'ai' => [
        'model'    => env('LANGFY_AI_MODEL', 'gpt-4o-mini'),
        'provider' => env('LANGFY_AI_PROVIDER', Prism\Prism\Enums\Provider::OpenAI),
    ],
];
