# String Discovery

## Introduction

Langfy automatically discovers translatable strings throughout your Laravel application by scanning PHP and Blade files. The discovery system recognizes various patterns and provides flexible configuration options for different project needs.

### How Discovery Works

The string discovery engine uses pattern matching to identify three types of translatable content:

-   **Translation functions** - Laravel's `__()`, `trans()`, and `@lang` calls
-   **Attributed properties** - Class properties marked with `#[Trans]`
-   **Annotated variables** - Variables marked with `/** @trans */`

### Scanning Process

During discovery, Langfy will:

1. Recursively scan specified directories for `.php` and `.blade.php` files
2. Apply pattern matching against file contents
3. Filter results using built-in and custom rules
4. Return deduplicated translatable strings

The following directories are excluded by default:

```
vendor/
node_modules/
storage/
bootstrap/cache/
public/
lang/
```

You may customize excluded content in the `langfy.php` configuration file using the comprehensive ignore system:

```php
<?php

return [
    'finder' => [
        // ...

        'ignore' => [
            'paths' => [
                'my-custom-directory',
                'vendor',
                'tests',
            ],
            'files' => [
                'config.php',
                'bootstrap.php',
            ],
            'namespaces' => [
                'App\\Tests',
                'Database\\',
            ],
            'strings' => [
                'debug',
                'test',
            ],
            'patterns' => [
                '/^test_/',
                '/debug$/',
            ],
            'extensions' => [
                'json',
                'md',
                'log',
            ],
        ],

        // ...
    ],
]
```

## Translation Functions

### Basic Functions

Langfy detects all standard Laravel translation function calls:

```php
// Global helper function
__('Welcome to our application')

// Translation facade
trans('messages.welcome')

// Blade directive
@lang('navigation.home')
```

### Functions with Parameters

Translation functions with parameters are fully supported:

```php
__('Welcome :name', ['name' => $user->name])
trans('You have :count messages', ['count' => $messageCount])

// Variable parameters
__('Hello :user', $translationParams)
```

### Ignored Translation Function

For cases where you want to use translation functions but don't want them to be discovered by the finder, use the `itrans()` function:

```php
// The finder will ignore this
itrans('colors.zinc')

// Perfect for PHP standard patterns or when you want manual control
itrans('messages.temporary')
itrans('debug.information')

// Works exactly like __() but won't be found during scanning
$message = itrans('user.welcome', ['name' => $user->name]);
```

**Use Cases for `itrans()`:**
- **PHP standard patterns**: When using dot notation like `itrans('colors.zinc')`
- **Temporary strings**: For debugging or temporary content
- **Manual translation control**: When you want to handle translation discovery manually
- **Dynamic keys**: When translation keys are built dynamically and shouldn't be auto-discovered

The `itrans()` function accepts the same parameters as Laravel's `__()` function.

## Property Patterns

### Using Attributes

Mark class properties as translatable using the `Trans` attribute:

```php
use Langfy\Trans;

class UserNotification
{
    #[Trans]
    public string $title = 'Account Created';

    #[Trans]
    protected string $message = 'Welcome to our platform';
}
```

The attribute works with all visibility levels and requires no parameters.

### Using Doc Comments

Alternatively, use doc comment annotations:

```php
class ErrorMessages
{
    /** @trans */
    public string $notFound = 'Resource not found';

    public string $success = 'Operation completed' /** @trans */;
}
```

Both block and inline doc comment styles are supported.

## Variable Patterns

Mark individual variables for translation using inline annotations:

```php
public function getMessages(): array
{
    $error = 'Invalid input provided' /** @trans */;
    $success = /** @trans */ 'Data saved successfully';

    return compact('error', 'success');
}
```

Variable annotations work in any scope - functions, methods, or global code.

## String Filtering

### Automatic Filtering

Langfy automatically excludes strings unlikely to need translation:

-   **Numeric values**: `123`, `45.67`
-   **Short identifiers**: `id`, `key`, `en`
-   **Domain-like patterns**: `example.com`, `user.email`
-   **Color codes**: `#ffffff`, `rgba(255,0,0,1)`
-   **Very short strings**: Less than 2 characters

```php
// These are automatically filtered out
$id = '123' /** @trans */;
$locale = 'en' /** @trans */;
$email = 'user@example.com' /** @trans */;

// These will be discovered
$message = 'Welcome back' /** @trans */;
$error = 'File not found' /** @trans */;
```

### Custom Filtering

Add custom filtering rules using the `filter()` method:

```php
$finder = new Finder();
$finder->filter(function (string $string): bool {
    // Skip strings that are only uppercase
    return $string !== strtoupper($string);
});
```

Multiple filters can be chained and will be applied in sequence.
