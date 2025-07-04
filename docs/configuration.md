# Configuration

## Overview

Langfy provides extensive configuration options to customize string discovery, AI translation, and general behavior.
All configuration is managed through the `config/langfy.php` file and environment variables.

## Publishing Configuration

After installing Langfy, publish the configuration file:

```bash
php artisan vendor:publish --tag="langfy-config"
```

This creates `config/langfy.php` with all available options and their default values.

## Configuration Structure

The configuration file is organized into several sections:

-   **[Context](#context)**: Additional context for AI translations
-   **[Languages](#languages)**: Source and target language settings
-   **[Finder](#finder-configuration)**: String discovery options
-   **[AI Translation](#ai-translation)**: AI provider and model settings

## Context

### Basic Context

Provide additional context to improve AI translation quality:

```php
'context' => 'E-commerce application for selling handmade crafts',
```

The context helps the AI understand your application's domain, resulting in more accurate translations.

### Usage Examples

```php
// E-commerce context
'context' => 'Online marketplace for vintage clothing and accessories',

// SaaS application context
'context' => 'Project management tool for software development teams',

// Educational platform context
'context' => 'Online learning platform for programming courses',
```

## Languages

### Source Language

Configure the language your application strings are written in:

```php
'from_language' => env('LANGFY_FROM_LANGUAGE', 'en'),
```

**Supported Values:**

-   Standard locale codes: `en`, `es`, `fr`, `de`, `pt`, etc.
-   Regional variants: `en_US`, `en_GB`, `pt_BR`, `es_ES`, etc.

### Target Languages

Specify which languages to translate to:

```php
'to_language' => [
    'es_ES', // Spanish (Spain)
    'pt_BR', // Portuguese (Brazil)
    'fr_FR', // French (France)
    'de_DE', // German (Germany)
]
```

## Finder Configuration

### Application Paths

Configure which directories to scan for translatable strings:

```php
'finder' => [
    'application_paths' => [
        base_path('app'),
        base_path('resources'),
        base_path('routes'),
        base_path('database'),
    ],
],
```

### Custom Paths

Add custom directories to scan:

```php
'application_paths' => [
    base_path('app'),
    base_path('resources'),
    base_path('routes'),
    base_path('database'),
    base_path('custom'),           // Custom directory
    base_path('packages/local'),   // Local packages
],
```

### Ignore Configuration

Langfy provides a comprehensive ignore system to exclude unwanted content from string discovery. The ignore configuration supports multiple types of filtering:

```php
'ignore' => [
    'files'      => [],  // Specific filenames to ignore
    'paths'      => [],  // Directory paths to ignore
    'namespaces' => [],  // PHP namespaces to ignore
    'strings'    => [],  // Specific strings to ignore
    'patterns'   => [],  // Regex patterns for flexible matching
    'extensions' => [],  // File extensions to ignore
],
```

#### Ignore Paths

Exclude specific directories from scanning:

```php
'ignore' => [
    'paths' => [
        'packages',
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        'public',           // Add public directory
        'tests',            // Exclude test files
        'docs',             // Exclude documentation
    ],
],
```

#### Ignore Extensions

Skip files with specific extensions:

```php
'ignore' => [
    'extensions' => [
        'json',
        'md',
        'txt',
        'log',
        'xml',              // Add XML files
        'yml',              // Add YAML files
        'yaml',             // Add YAML files
        'env',              // Add environment files
    ],
],
```

#### Ignore Files

Exclude specific filenames from scanning:

```php
'ignore' => [
    'files' => [
        'config.php',       // Skip configuration files
        'bootstrap.php',    // Skip bootstrap files
        'autoload.php',     // Skip autoload files
    ],
],
```

#### Ignore Namespaces

Exclude PHP files by namespace:

```php
'ignore' => [
    'namespaces' => [
        'App\\Tests',       // Skip test namespaces
        'Vendor\\Package',  // Skip vendor packages
        'Database\\',       // Skip database files
    ],
],
```

#### Ignore Strings

Exclude specific translatable strings:

```php
'ignore' => [
    'strings' => [
        'debug',            // Skip debug strings
        'test',             // Skip test strings
        'temp',             // Skip temporary strings
        'TODO',             // Skip TODO comments
    ],
],
```

#### Ignore Patterns

Use regex patterns for flexible string matching:

```php
'ignore' => [
    'patterns' => [
        '/^test_/',         // Skip strings starting with "test_"
        '/debug$/',         // Skip strings ending with "debug"
        '/temp\d+/',        // Skip strings like "temp1", "temp2"
        '/^[A-Z_]+$/',      // Skip constant-like strings
    ],
],
```

#### Combined Configuration

You can combine multiple ignore types for comprehensive filtering:

```php
'ignore' => [
    'files' => ['config.php', 'bootstrap.php'],
    'paths' => ['vendor', 'tests', 'storage'],
    'namespaces' => ['App\\Tests', 'Database\\'],
    'strings' => ['debug', 'test', 'temp'],
    'patterns' => ['/^test_/', '/debug$/'],
    'extensions' => ['json', 'md', 'log'],
],
```

## AI Translation

### API Configuration

Configure your AI provider settings:

```php
'ai' => [
    'api_key'     => env('LANGFY_AI_API_KEY', ''),
    'model'       => env('LANGFY_AI_MODEL', 'gpt-4o-mini'),
    'provider'    => env('LANGFY_AI_PROVIDER', Prism\Prism\Enums\Provider::OpenAI),
    'temperature' => env('LANGFY_AI_TEMPERATURE', 0.2),
],
```

### Environment Variables

```env
# OpenAI API key
LANGFY_AI_API_KEY=sk-your-openai-api-key-here

# AI model to use
LANGFY_AI_MODEL=gpt-4o-mini

# AI provider
LANGFY_AI_PROVIDER=openai

# Translation creativity (0.0 = deterministic, 1.0 = creative)
LANGFY_AI_TEMPERATURE=0.2
```

### Temperature Settings

Control translation creativity and consistency:

```php
// Deterministic translations (recommended)
'temperature' => 0.0,

// Slightly creative (good balance)
'temperature' => 0.2,

// More creative translations
'temperature' => 0.5,

// Highly creative (less consistent)
'temperature' => 1.0,
```

**Recommendations:**

-   **0.0-0.2**: Best for consistent, professional translations
-   **0.3-0.5**: Good for creative content that needs variety
-   **0.6-1.0**: Use sparingly, may produce inconsistent results
