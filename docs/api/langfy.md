# Langfy Class

-   [Introduction](#introduction)
    -   [Creating Langfy Instances](#creating-langfy-instances)
    -   [Context Types](#context-types)
-   [Available Methods](#available-methods)
-   [Configuration Methods](#configuration-methods)
-   [Execution Methods](#execution-methods)
-   [Progress Callbacks](#progress-callbacks)
-   [Usage Examples](#usage-examples)

## Introduction

The `Langfy\Langfy` class provides a fluent, convenient interface for finding and translating strings in Laravel applications and modules. It serves as the main entry point for all string discovery and AI-powered translation operations in the Langfy package.

For example, check out the following code. We'll use the `Langfy::for()` method to create a new instance for the application context, enable the finder functionality, configure it to save results, and then perform the operation:

```php
use Langfy\Enums\Context;
use Langfy\Langfy;

$result = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->perform();

// Returns: ['found_strings' => 25, 'saved' => true]
```

As you can see, the `Langfy` class allows you to chain its methods to perform fluent configuration and execution of string finding and translation operations. The class is designed to work with both Laravel applications and Laravel Modules seamlessly.

<a name="creating-langfy-instances"></a>

### Creating Langfy Instances

The primary way to create a Langfy instance is using the static `for` method, which requires a context and optionally a module name:

```php
use Langfy\Enums\Context;
use Langfy\Langfy;

// For application context
$langfy = Langfy::for(Context::Application);

// For module context
$langfy = Langfy::for(Context::Module, 'UserManagement');
```

You may also use the global `langfy()` helper function:

```php
// Using the helper function
$langfy = langfy(Context::Application);
$moduleInstance = langfy(Context::Module, 'UserManagement');
```

### Context Types

Langfy supports two main contexts for operation:

**Application Context (`Context::Application`)**

-   Processes the main Laravel application
-   Scans configured application paths (app, resources, routes, database)
-   Saves language files to the application's `lang` directory

**Module Context (`Context::Module`)**

-   Processes individual Laravel Modules (nwidart/laravel-modules)
-   Requires a module name as the second parameter
-   Scans the specific module's directory
-   Saves language files to the module's `lang` directory

## Available Methods

For the majority of the Langfy documentation, we'll discuss each method available on the `Langfy` class. Remember, all of these methods may be chained to fluently configure and execute your string finding and translation operations:

- [for](#method-for)
- [finder](#method-finder)
- [save](#method-save)
- [translate](#method-translate)
- [async](#method-async)
- [onFinderProgress](#method-onFinder-progress)
- [onTranslateProgress](#method-onTranslate-progress)
- [getStrings](#method-getStrings)
- [perform](#method-perform)
- [utils](#method-utils)

## Configuration Methods

#### `for()`

The static `for` method creates a new Langfy instance for the specified context. This is the primary way to instantiate the Langfy class:

```php
use Langfy\Enums\Context;
use Langfy\Langfy;

// Application context
$langfy = Langfy::for(Context::Application);

// Module context
$langfy = Langfy::for(Context::Module, 'UserManagement');
```

The method accepts two parameters:

-   `$context` (Context): The context type - either `Context::Application` or `Context::Module`
-   `$moduleName` (string|null): Required when using `Context::Module`, ignored for `Context::Application`

#### `finder()`

The `finder` method enables the string finding functionality. When enabled, Langfy will scan files for translatable strings using intelligent pattern matching:

```php
$langfy = Langfy::for(Context::Application)
    ->finder(); // Enable finder

$langfy = Langfy::for(Context::Application)
    ->finder(false); // Disable finder
```

The finder scans for strings in various formats:

-   `__('string')` and `__("string")` function calls
-   `trans('string')` and `trans("string")` function calls
-   `@lang('string')` Blade directives
-   `$this->lang` property access patterns

#### `save()`

The `save` method enables automatic saving of found strings to language files. Found strings are saved as JSON files in the appropriate language directory:

```php
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save(); // Enable saving

$langfy = Langfy::for(Context::Module, 'Blog')
    ->finder()
    ->save(false); // Disable saving
```

**Save Locations:**

-   **Application context**: `lang/{language}.json` (e.g., `lang/en.json`)
-   **Module context**: `Modules/{ModuleName}/lang/{language}.json`

#### `translate()`

The `translate` method enables AI-powered translation of found strings. It can translate to one or multiple target languages:

```php
// Enable translation with default target languages from config
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate();

// Translate to specific language
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: 'pt_BR');

// Translate to multiple languages
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['pt_BR', 'es_ES', 'fr_FR']);

// Disable translation
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(false);
```

The translation uses AI providers configured in your `langfy.ai` configuration and supports:

-   OpenAI GPT models
-   Automatic chunking for large string sets
-   Retry logic with exponential backoff
-   Progress tracking and callbacks

#### `async()`

The `async` method enables asynchronous translation processing using Laravel's queue system. When enabled, translation jobs are dispatched to queues instead of running synchronously:

```php
// Enable async translation
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['pt_BR', 'es_ES'])
    ->async(); // Enable async mode

// Disable async translation (default behavior)
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['pt_BR', 'es_ES'])
    ->async(false); // Disable async mode
```

**Async Mode Benefits:**

-   **Non-blocking execution**: Commands return immediately after dispatching jobs
-   **Better resource management**: Large translation batches don't consume memory for extended periods
-   **Scalability**: Multiple queue workers can process translations in parallel
-   **Fault tolerance**: Failed jobs can be retried automatically

**Requirements for Async Mode:**

-   Laravel queue system must be configured and running
-   Queue workers must be active to process the jobs
-   `TranslateStringsJob` will be dispatched for each target language

**Return Behavior:**

When async mode is enabled, the `perform()` method returns job dispatch information instead of actual translations:

```php
$result = Langfy::for(Context::Application)
    ->translate(to: ['pt_BR', 'es_ES'])
    ->async()
    ->perform();

// Returns:
// [
//     'translations' => [
//         'pt_BR' => ['job_dispatched' => true, 'strings_count' => 25],
//         'es_ES' => ['job_dispatched' => true, 'strings_count' => 25]
//     ]
// ]
```

## Progress Callbacks

#### `onFinderProgress()`

The `onFinderProgress` method allows you to register a callback that will be called during the string finding process, providing real-time progress updates:

```php
$langfy = Langfy::for(Context::Module, 'Blog')
    ->finder()
    ->onFinderProgress(function (int $current, int $total, array $extraData = []) {
        $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
        echo "Processing: {$current}/{$total} ({$percentage}%) - {$extraData['file']}\n";
    })
    ->perform();
```

**Callback Parameters:**

-   `$current` (int): Current item being processed
-   `$total` (int): Total number of items to process
-   `$extraData` (array): Additional data, typically contains `['file' => 'current_file_path']`

#### `onTranslateProgress()`

The `onTranslateProgress` method allows you to register a callback for translation progress updates:

```php
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['pt_BR', 'es_ES'])
    ->onTranslateProgress(function (int $current, int $total, array $extraData = []) {
        $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
        $language = $extraData['language'] ?? 'unknown';
        echo "Translating to {$language}: {$current}/{$total} ({$percentage}%)\n";
    })
    ->perform();
```

**Callback Parameters:**

-   `$current` (int): Current chunk being translated
-   `$total` (int): Total number of chunks to translate
-   `$extraData` (array): Contains `['language' => 'target_language']`

## Execution Methods

#### `getStrings()`

The `getStrings` method performs string finding and returns the discovered strings without saving or translating them. This is useful for inspection or custom processing:

```php
$langfy = Langfy::for(Context::Application);
$strings = $langfy->getStrings();

// Returns array of found strings:
// [
//     'Welcome to our application' => 'Welcome to our application',
//     'User not found' => 'User not found',
//     'Please enter your email' => 'Please enter your email'
// ]
```

> [!NOTE]
> If the finder is not explicitly enabled, `getStrings()` will automatically enable it before execution.

#### `perform()`

The `perform` method executes all configured operations and returns a comprehensive result array:

```php
$result = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['pt_BR', 'es_ES'])
    ->perform();

// Returns:
// [
//     'found_strings' => 25,
//     'saved' => true,
//     'translations' => [
//         'pt_BR' => ['Welcome' => 'Bem-vindo', ...],
//         'es_ES' => ['Welcome' => 'Bienvenido', ...]
//     ]
// ]
```

**Return Array Structure:**

-   `found_strings` (int): Number of strings found (if finder enabled)
-   `saved` (bool): Whether strings were saved (if save enabled)
-   `translations` (array): Translation results keyed by language (if translate enabled)

<a name="method-utils"></a>

<a name="usage-examples"></a>

## Usage Examples

### Basic String Finding

Find and save translatable strings in your application:

```php
use Langfy\Enums\Context;
use Langfy\Langfy;

$result = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->perform();

echo "Found {$result['found_strings']} translatable strings";
```

### Module Processing with Progress

Process a specific module with real-time progress updates:

```php
$result = Langfy::for(Context::Module, 'UserManagement')
    ->finder()
    ->save()
    ->onFinderProgress(function ($current, $total, $extraData) {
        $percentage = round(($current / $total) * 100, 1);
        echo "Processing: {$percentage}% - {$extraData['file']}\n";
    })
    ->perform();
```

### Complete Translation Workflow

Find strings and translate them to multiple languages:

```php
$result = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['pt_BR', 'es_ES', 'fr_FR'])
    ->onFinderProgress(function ($current, $total, $extraData) {
        echo "Finding strings: {$current}/{$total}\n";
    })
    ->onTranslateProgress(function ($current, $total, $extraData) {
        $lang = $extraData['language'];
        echo "Translating to {$lang}: {$current}/{$total}\n";
    })
    ->perform();

echo "Found {$result['found_strings']} strings\n";
echo "Translated to " . count($result['translations']) . " languages\n";
```

### String Inspection Only

Get strings without saving or translating:

```php
$strings = Langfy::for(Context::Application)->getStrings();

foreach ($strings as $key => $value) {
    echo "Found: {$value}\n";
}
```

### Conditional Operations

Chain operations conditionally:

```php
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save();

// Conditionally translate based on configuration
$shouldTranslate = config('myapp.shouldTranslate', true);

if ($shouldTranslate) {
    $langfy->translate();
}

$result = $langfy->perform();
```

### Working with Multiple Modules

Process multiple modules in sequence:

```php
$modules = ['UserManagement', 'Blog', 'Commerce'];
$totalStrings = 0;

foreach ($modules as $module) {
    $result = Langfy::for(Context::Module, $module)
        ->finder()
        ->save()
        ->translate()
        ->perform();

    $totalStrings += $result['found_strings'];
    echo "Module {$module}: {$result['found_strings']} strings\n";
}

echo "Total strings found: {$totalStrings}\n";
```

### Asynchronous Translation Workflow

Use async mode for large translation batches:

```php
// Dispatch translation jobs to queue
$result = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['pt_BR', 'es_ES', 'fr_FR'])
    ->async()
    ->perform();

echo "Found {$result['found_strings']} strings\n";

foreach ($result['translations'] as $language => $jobInfo) {
    if ($jobInfo['job_dispatched']) {
        echo "Dispatched job for {$language}: {$jobInfo['strings_count']} strings\n";
    }
}
```

### Conditional Async Processing

Enable async mode based on string count:

```php
$langfy = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['pt_BR', 'es_ES']);

// Get string count first
$strings = $langfy->getStrings();
$stringCount = count($strings);

// Use async for large batches
if ($stringCount > 100) {
    $langfy->async();
    echo "Using async mode for {$stringCount} strings\n";
} else {
    echo "Using sync mode for {$stringCount} strings\n";
}

$result = $langfy->perform();
```

> [!NOTE]
> All methods support method chaining, allowing you to build complex workflows with readable, fluent syntax. The order of method calls doesn't matter for configuration methods (`finder`, `save`, `translate`), but `perform()` or `getStrings()` should always be called last to execute the configured operations.
