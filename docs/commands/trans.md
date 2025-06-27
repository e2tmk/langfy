# Trans

## Introduction

The `langfy:trans` command translates strings in your Laravel application or modules using AI-powered translation services. This command provides flexible targeting options and supports multiple target languages for efficient batch translation operations.

## Basic Usage

Run the trans command without options for interactive mode:

```shell
php artisan langfy:trans
```

The command will prompt you to select target areas (application and/or modules) and use the configured target languages from your configuration.

## Command Options

### Target Languages

Specify target languages for translation:

```shell
# Translate to specific languages
php artisan langfy:trans --to=es_ES,pt_BR

# Translate to multiple languages
php artisan langfy:trans --to=fr_FR,de_DE,it_IT
```

If the `--to` option is not provided, the command uses the languages configured in `config/langfy.php`.

### Application Mode

Process only the main Laravel application:

```shell
php artisan langfy:trans --app
```

This translates strings found in the main application directory structure.

### Module Mode

Process specific modules when using [Laravel Modules](https://nwidart.com/laravel-modules/):

```shell
# Process specific modules
php artisan langfy:trans --modules=User,Product,Order

# Process single module
php artisan langfy:trans --modules=User
```

The command automatically detects if Laravel Modules is available and enabled.

### Queue Mode

Run translations asynchronously using Laravel's queue system:

```shell
# Run translations in background using queues
php artisan langfy:trans --queue

# Combine with other options
php artisan langfy:trans --to=es_ES,pt_BR --app --queue
php artisan langfy:trans --modules=User,Product --queue
```

When using the `--queue` option, translation jobs are dispatched to Laravel's queue system instead of running synchronously. This is particularly useful for:

- Large translation batches that might timeout
- Background processing to avoid blocking the command line
- Better resource management in production environments

**Queue Requirements:**
- Laravel queue system must be configured and running
- Queue workers must be active to process the jobs

### Combined Options

Combine different options for precise control:

```shell
# Translate app and specific modules to custom languages
php artisan langfy:trans --to=es_ES,pt_BR --app --modules=User,Product

# Translate only modules with default languages
php artisan langfy:trans --modules=Blog,Commerce

# Run translations asynchronously
php artisan langfy:trans --to=es_ES,pt_BR --app --modules=User,Product --queue
```

## Interactive Mode

### Target Selection

When run without specific options, the command provides interactive prompts:

```shell
php artisan langfy:trans
```

**Application Selection:**

```
Do you want to process the main application? (yes/no) [yes]:
```

**Module Selection:**

```
Which modules do you want to process? (Use comma to separate multiple)
  [0] None
  [1] All
  [2] User
  [3] Product
  [4] Order
```

You can select multiple modules by providing comma-separated indices:

```
> 2,3
```

## Configuration

The command reads default settings from `config/langfy.php`:

```php
return [
    'to_language' => ['es_ES', 'pt_BR', 'fr_FR'],
    'ai' => [
        'api_key' => env('LANGFY_AI_API_KEY', ''),
        'model' => env('LANGFY_AI_MODEL', 'gpt-4o-mini'),
        'provider' => env('LANGFY_AI_PROVIDER', 'openai'),
        'temperature' => env('LANGFY_AI_TEMPERATURE', 0.2),
    ],
    // ...
];
```

**Required Configuration:**

- `to_language`: Array of target language codes
- `ai.api_key`: API key for the AI translation service
- `ai.model`: AI model to use for translations

## Batch Processing

Process multiple targets efficiently:

```shell
# Process app and all modules with custom languages
php artisan langfy:trans --to=es_ES,pt_BR --app --modules=User,Product

# Process all modules with default languages
php artisan langfy:trans --modules=User,Product,Order
```

The command optimizes processing order and provides progress feedback for each target and language combination.

## Output and Reporting

The command provides detailed output throughout execution:

**Translation Progress (Synchronous):**

```
Target languages: es_ES, pt_BR
Starting in "User Module"
Translating User to es_ES: 8/8 (100%)
Translating User to pt_BR: 8/8 (100%)
Translated 16 strings in User Module

Starting in "Product Module"
Translating Product to es_ES: 12/12 (100%)
Translating Product to pt_BR: 12/12 (100%)
Translated 24 strings in Product Module
```

**Translation Progress (Asynchronous with --queue):**

```
Target languages: es_ES, pt_BR
Starting in "User Module"
Dispatching async translation jobs for User Module...
Dispatched 2 translation jobs for 8 strings in User Module

Starting in "Product Module"
Dispatching async translation jobs for Product Module...
Dispatched 2 translation jobs for 12 strings in Product Module
```

**Summary Table:**

```
┏━━━━━━━━━━━━━┳━━━━━━━━━━━━━━━━━━━━━━┓
┃ Area        ┃ Translations Created ┃
┡━━━━━━━━━━━━━╇━━━━━━━━━━━━━━━━━━━━━━┩
│ Application │ 30                   │
│ User        │ 16                   │
│ Product     │ 24                   │
└─────────────┴──────────────────────┘

70 translations created in total
```

## AI Translation Features

The command leverages advanced AI translation capabilities:

**Intelligent Translation:**
- Context-aware translations using AI models
- Maintains consistency across related strings
- Preserves placeholders and formatting

**Progress Tracking:**
- Real-time progress updates for each language
- Detailed feedback on translation status
- Error handling with retry logic

**Batch Processing:**
- Automatic chunking for large string sets
- Optimized API usage to reduce costs
- Parallel processing when possible

## Error Handling

The command includes robust error handling:

```shell
# Missing configuration
No target languages specified. Please use --to option or configure langfy.to_language

# API errors
Translation failed for es_ES: API rate limit exceeded. Retrying in 5 seconds...

# Module errors
Module "NonExistent" not found or not enabled
```

## Integration with Finder

The trans command works seamlessly with the finder command:

```shell
# First, find strings
php artisan langfy:finder --app --modules=User

# Then translate them
php artisan langfy:trans --to=es_ES,pt_BR --app --modules=User
```

Or combine both operations using the finder command's built-in translation feature:

```shell
php artisan langfy:finder --app --modules=User --trans
```

## Best Practices

**Language Codes:**
Use standard locale codes (e.g., `es_ES`, `pt_BR`, `fr_FR`) for consistency with Laravel's localization system.

**API Key Security:**
Store your AI API key securely using environment variables:

```env
LANGFY_AI_API_KEY=your-api-key-here
```

**Batch Operations:**
For large projects, process modules individually to monitor progress and handle any issues:

```shell
php artisan langfy:trans --modules=User --to=es_ES
php artisan langfy:trans --modules=Product --to=es_ES
```

**Configuration Management:**
Set up your default target languages in the configuration file to avoid specifying them repeatedly:

```php
'to_language' => ['es_ES', 'pt_BR', 'fr_FR', 'de_DE'],
```
