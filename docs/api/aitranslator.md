# AITranslator Class

- [Introduction](#introduction)
- [Usage](#usage)
- [Methods](#methods)
- [Progress Callbacks](#progress-callbacks)

## Introduction

The `Langfy\Services\AITranslator` class provides AI-powered translation services for your application. It can translate an array of strings into one or more target languages using a configured AI provider.

The `AITranslator` class is designed to be robust, with features like automatic chunking of large string sets, retry logic with exponential backoff, and progress tracking.

## Usage

You can use the `AITranslator` class to translate your strings. Here's a basic example:

```php
use Langfy\Services\AITranslator;

$translations = AITranslator::configure()
    ->to('pt_BR')
    ->run(['Hello' => 'Hello', 'World' => 'World']);

// Returns: ['Hello' => 'Olá', 'World' => 'Mundo']
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

The `chunkSize()` method sets the number of strings to be translated in each chunk.

```php
$translator = AITranslator::configure()->chunkSize(10);
```

### `run()`

The `run()` method starts the translation process and returns an array of translated strings.

```php
$translations = AITranslator::configure()
    ->to('pt_BR')
    ->run(['Hello' => 'Hello', 'World' => 'World']);
```

### `quickTranslate()`

The static `quickTranslate()` method provides a convenient way to translate strings without fluently configuring the translator.

```php
$translations = AITranslator::quickTranslate(
    ['Hello' => 'Hello', 'World' => 'World'],
    'pt_BR'
);
```

## Progress Callbacks

### `onProgress()`

The `onProgress()` method allows you to register a callback that will be called during the translation process, providing real-time progress updates.

```php
$translator = AITranslator::configure()
    ->to('pt_BR')
    ->onProgress(function (int $current, int $total, array $extraData = []) {
        $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
        $language = $extraData['language'] ?? 'unknown';
        echo "Translating to {$language}: {$current}/{$total} ({$percentage}%)\n";
    });

$translations = $translator->run(['Hello' => 'Hello', 'World' => 'World']);
```