# Introduction

## What is Langfy?

Langfy is a powerful Laravel package designed to streamline the translation workflow in Laravel applications. It combines intelligent string discovery with AI-powered translation services to automate the traditionally manual process of internationalization (i18n).

<div class="features-grid">
  <div class="feature-card">
    <div class="feature-icon">🔍</div>
    <h3>Smart String Discovery</h3>
    <ul>
      <li>Automatically finds translatable strings in PHP and Blade files</li>
      <li>Supports multiple pattern types: functions, attributes, and annotations</li>
      <li>Intelligent filtering to exclude non-translatable content</li>
      <li>Configurable scanning paths and exclusion rules</li>
    </ul>
  </div>

  <div class="feature-card">
    <div class="feature-icon">🤖</div>
    <h3>AI-Powered Translation</h3>
    <ul>
      <li>Integration with OpenAI and other AI providers</li>
      <li>Context-aware translations that maintain meaning</li>
      <li>Automatic chunking for large translation batches</li>
      <li>Retry logic with exponential backoff for reliability</li>
    </ul>
  </div>

  <div class="feature-card">
    <div class="feature-icon">📦</div>
    <h3>Module Support</h3>
    <ul>
      <li>Full compatibility with Laravel Modules (nwidart/laravel-modules)</li>
      <li>Individual module processing and management</li>
      <li>Separate language file generation per module</li>
    </ul>
  </div>

  <div class="feature-card">
    <div class="feature-icon">🔗</div>
    <h3>Fluent API</h3>
    <ul>
      <li>Chainable methods for clean, readable code</li>
      <li>Intuitive configuration and execution</li>
      <li>Progress callbacks for real-time feedback</li>
    </ul>
  </div>
</div>

## Quick Start

### Installation

Install Langfy via Composer:

```bash
composer require e2tmk/langfy --dev
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
