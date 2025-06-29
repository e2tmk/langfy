# AITranslator Class

-   [Introduction](#introduction)
-   [Usage](#usage)
-   [Methods](#methods)
-   [Process Pool](#process-pool)

## Introduction

The `Langfy\Services\AITranslator` class provides AI-powered translation services for your application. It can translate an array of strings into one or more target languages using a configured AI provider.

The `AITranslator` class is designed to be robust and performant, with features like:

-   **Process Pool parallelism** for concurrent translation processing (enabled by default)
-   Automatic chunking of large string sets
-   Retry logic with exponential backoff
-   Progress tracking
-   Multiple language support with concurrent processing

## Usage

You can use the `AITranslator` class to translate your strings. Here's a basic example:

```php
use Langfy\Services\AITranslator;

$translations = AITranslator::configure()
    ->to('pt_BR')
    ->run(['Hello' => 'Hello', 'World' => 'World']);

// Returns: ['Hello' => 'Olá', 'World' => 'Mundo']
```

### Process Pool (Default Behavior)

By default, the AITranslator uses Process Pool for parallel processing, which significantly improves performance:

```php
// Process Pool enabled by default (recommended)
$translations = AITranslator::configure()
    ->to('pt_BR')
    ->run($largeStringArray);

// Customize concurrency
$translations = AITranslator::configure()
    ->withProcessPool(true, 5) // 5 concurrent processes
    ->to('pt_BR')
    ->run($largeStringArray);

// Disable Process Pool if needed
$translations = AITranslator::configure()
    ->withProcessPool(false)
    ->to('pt_BR')
    ->run($strings);
```

### Multiple Languages

When translating to multiple languages, each language is processed in parallel:

```php
// Parallel processing for multiple languages
$translations = AITranslator::quickTranslate(
    $strings,
    ['pt_BR', 'es_ES', 'fr_FR']
);

// Returns:
// [
//     'pt_BR' => ['Hello' => 'Olá', 'World' => 'Mundo'],
//     'es_ES' => ['Hello' => 'Hola', 'World' => 'Mundo'],
//     'fr_FR' => ['Hello' => 'Bonjour', 'World' => 'Monde']
// ]
```

## Methods

### `configure()`

The static `configure()` method creates a new `AITranslator` instance with the default configuration.

```php
$translator = AITranslator::configure();
```

### `from()`

The `from()` method sets the source language for the translation.

```php
$translator = AITranslator::configure()->from('en');
```

### `to()`

The `to()` method sets the target language for the translation.

```php
$translator = AITranslator::configure()->to('pt_BR');
```

### `model()`

The `model()` method sets the AI model to be used for the translation.

```php
$translator = AITranslator::configure()->model('gpt-4');
```

### `temperature()`

The `temperature()` method sets the temperature for the AI model.

```php
$translator = AITranslator::configure()->temperature(0.7);
```

### `provider()`

The `provider()` method sets the AI provider to be used for the translation.

```php
use Prism\Prism\Enums\Provider;

$translator = AITranslator::configure()->provider(Provider::OpenAI);
```

### `chunkSize()`

The `chunkSize()` method sets the number of strings to be translated in each chunk. Larger chunks can improve performance but use more memory.

```php
$translator = AITranslator::configure()->chunkSize(25);
```

### `withProcessPool()`

The `withProcessPool()` method controls whether to use parallel processing and sets the maximum number of concurrent processes.

```php
// Enable with default concurrency (3 processes)
$translator = AITranslator::configure()->withProcessPool(true);

// Enable with custom concurrency
$translator = AITranslator::configure()->withProcessPool(true, 5);

// Disable parallel processing
$translator = AITranslator::configure()->withProcessPool(false);
```

### `onSave()`

The `onSave()` method registers a callback that will be called immediately when each chunk is translated, allowing for real-time saving of results.

```php
$translator = AITranslator::configure()
    ->onSave(function (array $chunkTranslations) {
        // Save translations immediately
        file_put_contents('translations.json',
            json_encode($chunkTranslations, JSON_PRETTY_PRINT)
        );
    });
```

### `run()`

The `run()` method starts the translation process and returns an array of translated strings.

```php
$translations = AITranslator::configure()
    ->to('pt_BR')
    ->run(['Hello' => 'Hello', 'World' => 'World']);
```

### `quickTranslate()`

The static `quickTranslate()` method provides a convenient way to translate strings without fluently configuring the translator. It automatically uses Process Pool for multiple languages.

```php
// Single language
$translations = AITranslator::quickTranslate(
    ['Hello' => 'Hello', 'World' => 'World'],
    'pt_BR'
);

// Multiple languages (processed in parallel)
$translations = AITranslator::quickTranslate(
    ['Hello' => 'Hello', 'World' => 'World'],
    ['pt_BR', 'es_ES', 'fr_FR']
);

// With custom source language
$translations = AITranslator::quickTranslate(
    ['Bonjour' => 'Bonjour', 'Monde' => 'Monde'],
    ['pt_BR', 'es_ES'],
    'fr' // from French
);
```

## Process Pool

The AITranslator uses Laravel's Process Pool to achieve true parallelism by running multiple PHP processes concurrently. This provides significant performance improvements, especially for:

-   Large datasets (hundreds or thousands of strings)
-   Multiple target languages
-   High-latency API calls

### How it Works

1. **Chunk Processing**: Large string arrays are divided into chunks
2. **Parallel Execution**: Each chunk is processed by a separate PHP process via `langfy:translate-chunk` command
3. **Concurrent Batches**: Multiple chunks are processed simultaneously (configurable concurrency)
4. **Result Aggregation**: Results from all processes are collected and merged

### Process Pool vs Sequential

```php
// Process Pool (default) - Fast, parallel processing
$translator = AITranslator::configure()
    ->withProcessPool(true, 3) // 3 concurrent processes
    ->chunkSize(20)
    ->to('pt_BR');

// Sequential processing - Slower, but uses less system resources
$translator = AITranslator::configure()
    ->withProcessPool(false)
    ->chunkSize(20)
    ->to('pt_BR');
```