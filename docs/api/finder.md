# Finder Class

- [Introduction](#introduction)
- [Usage](#usage)
- [Methods](#methods)
- [Progress Callbacks](#progress-callbacks)

## Introduction

The `Langfy\Services\Finder` class is responsible for scanning your application or module files to find translatable strings. It uses a set of predefined patterns to identify strings that should be translated, such as those wrapped in `__()`, `trans()`, or `@lang()` calls.

The `Finder` class is highly configurable, allowing you to specify which paths to scan, which paths to ignore, and which file extensions to exclude from scanning.

## Usage

You can use the `Finder` class directly to find translatable strings in your project. Here's a basic example:

```php
use Langfy\Services\Finder;

$strings = Finder::in(base_path('app'))
    ->ignore('app/Http/Controllers/Api')
    ->run();

// Returns an array of unique translatable strings found in the app directory.
```

## Methods

### `in()`

The static `in()` method creates a new `Finder` instance and sets the initial paths to scan.

```php
// Scan a single path
$finder = Finder::in(base_path('app'));

// Scan multiple paths
$finder = Finder::in([
    base_path('app'),
    base_path('resources'),
]);
```

### `and()`

The `and()` method adds more paths to the list of paths to be scanned.

```php
$finder = Finder::in(base_path('app'))
    ->and(base_path('resources'));
```

### `ignore()`

The `ignore()` method allows you to exclude specific paths from being scanned.

```php
$finder = Finder::in(base_path('app'))
    ->ignore('app/Http/Controllers/Api');
```

### `ignoreExtensions()`

The `ignoreExtensions()` method allows you to specify file extensions to ignore during the scan.

```php
$finder = Finder::in(base_path('app'))
    ->ignoreExtensions('log');

// Multiple extensions
$finder = Finder::in(base_path('app'))
    ->ignoreExtensions(['json', 'md', 'log']);
```

### `ignoreFiles()`

The `ignoreFiles()` method allows you to exclude specific filenames from being scanned.

```php
$finder = Finder::in(base_path('app'))
    ->ignoreFiles('config.php');

// Multiple files
$finder = Finder::in(base_path('app'))
    ->ignoreFiles(['config.php', 'bootstrap.php']);
```

### `ignoreNamespaces()`

The `ignoreNamespaces()` method allows you to exclude PHP files by their namespace.

```php
$finder = Finder::in(base_path('app'))
    ->ignoreNamespaces('App\\Tests');

// Multiple namespaces
$finder = Finder::in(base_path('app'))
    ->ignoreNamespaces(['App\\Tests', 'Database\\']);
```

### `ignoreStrings()`

The `ignoreStrings()` method allows you to exclude specific translatable strings from the results.

```php
$finder = Finder::in(base_path('app'))
    ->ignoreStrings('debug');

// Multiple strings
$finder = Finder::in(base_path('app'))
    ->ignoreStrings(['debug', 'test', 'temp']);
```

### `ignorePatterns()`

The `ignorePatterns()` method allows you to exclude strings using regex patterns for flexible matching.

```php
$finder = Finder::in(base_path('app'))
    ->ignorePatterns('/^test_/');

// Multiple patterns
$finder = Finder::in(base_path('app'))
    ->ignorePatterns(['/^test_/', '/debug$/', '/temp\d+/']);
```

### Chaining Ignore Methods

All ignore methods can be chained together for comprehensive filtering:

```php
$finder = Finder::in(base_path('app'))
    ->ignore('vendor')
    ->ignoreFiles(['config.php', 'bootstrap.php'])
    ->ignoreNamespaces(['App\\Tests'])
    ->ignoreStrings(['debug', 'test'])
    ->ignorePatterns(['/^test_/', '/debug$/'])
    ->ignoreExtensions(['json', 'md']);
```

### `run()`

The `run()` method starts the scanning process and returns an array of unique translatable strings found.

```php
$strings = Finder::in(base_path('app'))->run();
```

## Progress Callbacks

### `onProgress()`

The `onProgress()` method allows you to register a callback that will be called during the scanning process, providing real-time progress updates.

```php
$finder = Finder::in(base_path('app'))
    ->onProgress(function (int $current, int $total, array $extraData = []) {
        $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
        echo "Processing: {$current}/{$total} ({$percentage}%) - {$extraData['file']}\n";
    });

$strings = $finder->run();
```
