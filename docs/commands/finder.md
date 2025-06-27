# Finder

## Introduction

The `langfy:finder` command automatically discovers translatable strings in your Laravel application or modules and optionally translates them using AI. This command provides flexible targeting options and interactive prompts for fine-grained control.

## Basic Usage

Run the finder command without options for interactive mode:

```shell
php artisan langfy:finder
```

The command will prompt you to select target areas (application and/or modules) and configure translation preferences.

## Command Options

### Application Mode

Process only the main Laravel application:

```shell
php artisan langfy:finder --app
```

This scans the main application directory structure, excluding vendor and other ignored paths.

### Module Mode

Process specific modules when using [Laravel Modules](https://nwidart.com/laravel-modules/):

```shell
# Process specific modules
php artisan langfy:finder --modules=User,Product,Order

# Process single module
php artisan langfy:finder --modules=User
```

The command automatically detects if Laravel Modules is available and enabled.

### Translation Options

Control translation behavior with these options:

```shell
# Skip translation prompt entirely
php artisan langfy:finder --no-trans

# Auto-translate without prompting
php artisan langfy:finder --trans

# Run translations asynchronously using queues
php artisan langfy:finder --trans --queue

# Combine options
php artisan langfy:finder --app --trans
php artisan langfy:finder --app --trans --queue
```

**Queue Mode:**

The `--queue` option runs translations asynchronously using Laravel's queue system. This is useful for:

- Large translation batches that might timeout
- Background processing to avoid blocking the command line
- Better resource management in production environments

**Queue Requirements:**
- Laravel queue system must be configured and running
- Queue workers must be active to process the jobs
- Must be used in combination with `--trans` option

## Interactive Mode

### Target Selection

When run without specific options, the command provides interactive prompts:

```shell
php artisan langfy:finder
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

### Translation Selection

After string discovery, select areas for translation:

```shell
Which areas do you want to translate? (Use comma to separate multiple)
  [0] None
  [1] All
  [2] Application (15 strings)
  [3] User (8 strings)
  [4] Product (12 strings)
```

Translation requires configured target languages in `config/langfy.php`:

```php
return [
    'to_language' => ['es', 'fr', 'de'],
    // ...
];
```

## Batch Processing

Process multiple targets efficiently:

```shell
# Process app and all modules with translation
php artisan langfy:finder --app --modules=User,Product --trans

# Process all modules without app
php artisan langfy:finder --modules=User,Product,Order --no-trans

# Process with asynchronous translation
php artisan langfy:finder --app --modules=User,Product --trans --queue
```

The command optimizes processing order and provides progress feedback for each target. When using `--queue`, translation jobs are dispatched to the background for processing.

## Output and Reporting

The command provides detailed output throughout execution:

**Discovery Progress:**

```
Starting in "User Module"
Found 8 translatable strings in User Module

Starting in "Product Module"
Found 12 translatable strings in Product Module
```

**Summary Table:**

```
┏━━━━━━━━━━━━━┳━━━━━━━━━━━━━━━┓
┃ Area        ┃ Strings Found ┃
┡━━━━━━━━━━━━━╇━━━━━━━━━━━━━━━┩
│ Application │ 15            │
│ User        │ 8             │
│ Product     │ 12            │
└─────────────┴───────────────┘

35 strings found in total
```

**Translation Progress (Synchronous):**

```
Translating found strings...
Target languages: es, fr, de

Translating User to es: 8/8 (100%)
Translating User to fr: 8/8 (100%)
Translating User to de: 8/8 (100%)
```

**Translation Progress (Asynchronous with --queue):**

```
Translating found strings...
Target languages: es, fr, de

Dispatching async translation jobs for User...
Dispatched 3 translation jobs for 8 strings in User
```
