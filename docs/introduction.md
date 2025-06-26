# Introduction

## What is Langfy?

Langfy is a powerful Laravel package designed to streamline the translation workflow in Laravel applications. It combines intelligent string discovery with AI-powered translation services to automate the traditionally manual process of internationalization (i18n).

### Key Features

**🔍 Smart String Discovery**
- Automatically finds translatable strings in PHP and Blade files
- Supports multiple pattern types: functions, attributes, and annotations
- Intelligent filtering to exclude non-translatable content
- Configurable scanning paths and exclusion rules

**🤖 AI-Powered Translation**
- Integration with OpenAI and other AI providers
- Context-aware translations that maintain meaning
- Automatic chunking for large translation batches
- Retry logic with exponential backoff for reliability

**📦 Module Support**
- Full compatibility with Laravel Modules (nwidart/laravel-modules)
- Individual module processing and management
- Separate language file generation per module

**🔗 Fluent API**
- Chainable methods for clean, readable code
- Intuitive configuration and execution
- Progress callbacks for real-time feedback

## Quick Start

### Installation

Install Langfy via Composer:

```bash
composer require e2tmk/langfy
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="langfy"
```

### Basic Usage

The simplest way to get started is using the Artisan commands:

```bash
# Find translatable strings in your application
php artisan langfy:finder --app

# Translate found strings to multiple languages
php artisan langfy:trans --to=es_ES,pt_BR --app
```

Or use the fluent API for programmatic control:

```php
use Langfy\Enums\Context;
use Langfy\Langfy;

// Find and translate strings in one operation
$result = Langfy::for(Context::Application)
    ->finder()
    ->save()
    ->translate(to: ['es_ES', 'pt_BR'])
    ->perform();

echo "Found {$result['found_strings']} strings";
echo "Translated to " . count($result['translations']) . " languages";
```
