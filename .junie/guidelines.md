# Langfy Laravel Package - AI Agent Guidelines

## Project Overview

**Langfy** is a Laravel package designed for AI-powered translation and string management. This package focuses on finding translatable strings in Laravel applications and translating them using AI services.

### Package Details
- **Name**: e2tmk/langfy
- **Type**: Laravel Package
- **PHP Version**: ^8.2
- **Laravel Support**: 10.x, 11.x, 12.x
- **Main Dependencies**: Spatie Laravel Package Tools, Prism PHP, Livewire

## Core Architecture

### Services
The package contains two main services:

#### 1. Finder Service (`Langfy\Services\Finder`)
**Purpose**: Locates translatable strings in PHP and Blade files using pattern matching.

**Key Features**:
- Scans directories for PHP and Blade files
- Uses pattern-based string extraction (FunctionPattern, PropertyPattern, VariablePattern)
- Supports fluent interface for configuration
- Implements intelligent filtering to skip non-translatable content

#### 2. AITranslator Service (`Langfy\Services\AITranslator`)
**Purpose**: Translates strings using AI providers with retry logic and chunking.

**Key Features**:
- Configurable AI models and providers
- Automatic chunking for large translation batches
- Retry logic with exponential backoff
- Fluent interface for configuration
- Integration with Laravel's dependency injection

### Providers

#### AIProvider (`Langfy\Providers\AIProvider`)
**Purpose**: Handles direct communication with AI services using Prism PHP.

**Key Features**:
- Structured AI responses using schemas
- Retry logic with proper error handling
- Dynamic API key configuration
- System prompt integration with Laravel views

## Coding Standards & Patterns

### 1. Laravel Helpers Usage
**CRITICAL**: Always use Laravel helpers instead of native PHP functions:

```php
// ✅ CORRECT - Use Laravel helpers
if (filled($variable)) {
    // Process filled variable
}

if (blank($array)) {
    return [];
}

// ❌ WRONG - Don't use native PHP
if (!empty($variable)) {
    // Process variable
}

if (empty($array)) {
    return [];
}
```

**Helper Mapping**:
- Use `filled()` instead of `!empty()`
- Use `blank()` instead of `empty()`
- Use `optional()` for safe property access
- Use `collect()` for array manipulation

### 2. Collection Usage
**MANDATORY**: Use Laravel Collections for all array manipulation:

```php
// ✅ CORRECT - Use Collections
$results = collect($strings)
    ->chunk($chunkSize)
    ->map(fn($chunk) => $this->processChunk($chunk))
    ->flatten()
    ->toArray();

// ✅ CORRECT - Collection methods
$filtered = collect($items)
    ->filter(fn($item) => filled($item))
    ->mapWithKeys(fn($item, $key) => [$key => $item])
    ->values();

// ❌ WRONG - Don't use array functions directly
$results = array_map('processItem', $strings);
$filtered = array_filter($items);
```

### 3. Early Return Pattern
**REQUIRED**: Implement early return patterns for cleaner code:

```php
// ✅ CORRECT - Early returns
public function processStrings(array $strings): array
{
    if (blank($strings)) {
        return [];
    }
    
    if (!$this->isConfigured()) {
        return [];
    }
    
    // Main processing logic here
    return $this->doProcessing($strings);
}

// ✅ CORRECT - Early continue in loops
foreach ($items as $item) {
    if (blank($item)) {
        continue;
    }
    
    if ($this->shouldSkip($item)) {
        continue;
    }
    
    // Process item
}
```

### 4. Language Requirements
**MANDATORY**: All code must be written in English:

```php
// ✅ CORRECT - English comments and names
/**
 * Find translatable strings in the given content.
 * 
 * @param string $content The content to search
 * @return array Found strings
 */
public function findStringsInContent(string $content): array
{
    // Skip if content is empty
    if (blank($content)) {
        return [];
    }
    
    // Process content and return results
}

// ❌ WRONG - Non-English content
public function encontrarStrings($conteudo): array
{
    // Processar conteúdo
}
```

### 5. Type Declarations
**REQUIRED**: Use strict typing throughout:

```php
// ✅ CORRECT - Always declare strict types
<?php

declare(strict_types = 1);

// ✅ CORRECT - Type all parameters and returns
public function translateChunk(Collection $chunk): array
{
    return $this->getAIProvider()->translate($chunk->toArray());
}

// ✅ CORRECT - Use union types when appropriate
public function provider(Provider | string $provider): AITranslator
{
    $this->modelProvider = $provider;
    return $this;
}
```

## Laravel-Specific Practices

### 1. Dependency Injection & Container
**USE**: Laravel's service container extensively:

```php
// ✅ CORRECT - Use app() helper
public static function configure(): AITranslator
{
    return app(AITranslator::class);
}

// ✅ CORRECT - Constructor injection with attributes
public function __construct(
    #[Config('langfy.from_language')]
    protected string $fromLanguage,
    #[Config('langfy.ai.model')]
    protected string $aiModel,
) {
}

// ✅ CORRECT - Manual container resolution
protected function getAIProvider(): AIProvider
{
    return $this->aiProvider ??= app(AIProvider::class, [
        'fromLanguage' => $this->fromLanguage,
        'toLanguage' => $this->toLanguage,
    ]);
}
```

### 2. Configuration Management
**USE**: Laravel's configuration system:

```php
// ✅ CORRECT - Use config() helper
$ignorePaths = config('langfy.finder.ignore_paths', []);
$apiKey = config()->string('langfy.ai.api_key');

// ✅ CORRECT - Environment variables in config
'from_language' => env('LANGFY_FROM_LANGUAGE', 'en'),
'ai' => [
    'api_key' => env('LANGFY_AI_API_KEY', ''),
    'model' => env('LANGFY_AI_MODEL', 'gpt-4o-mini'),
],
```

### 3. Facades Usage
**USE**: Laravel facades appropriately:

```php
// ✅ CORRECT - Use facades for Laravel services
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

// File operations
File::ensureDirectoryExists(dirname($filePath));
if (!File::exists($filePath)) {
    File::put($filePath, '');
}

// Logging
Log::error("Translation failed after {$maxRetries} attempts: " . $exception->getMessage());
```

### 4. Fluent Interface Pattern
**IMPLEMENT**: Fluent interfaces for better UX:

```php
// ✅ CORRECT - Fluent interface
$translations = AITranslator::configure()
    ->from('en')
    ->to('pt_BR')
    ->model('gpt-4o-mini')
    ->temperature(0.2)
    ->chunkSize(10)
    ->run($strings);

$strings = Finder::in(['app', 'resources'])
    ->and('modules')
    ->ignore(['vendor', 'storage'])
    ->ignoreExtensions(['json', 'md'])
    ->run();
```

## Error Handling & Logging

### 1. Exception Handling
**IMPLEMENT**: Proper exception handling with retries:

```php
// ✅ CORRECT - Retry logic with proper exception handling
$attempt = 0;
$lastException = null;

while ($attempt < $this->maxRetries) {
    try {
        return $this->executeOperation();
    } catch (Throwable $e) {
        $lastException = $e;
        $attempt++;
        
        if ($attempt < $this->maxRetries) {
            sleep($this->retryDelay * $attempt);
        }
    }
}

Log::error("Operation failed after {$this->maxRetries} attempts: " . $lastException->getMessage());
return [];
```

### 2. Logging Standards
**USE**: Laravel's logging with appropriate levels:

```php
// ✅ CORRECT - Appropriate log levels
Log::error("Critical failure: " . $exception->getMessage());
Log::warning("Retrying operation due to: " . $exception->getMessage());
Log::info("Processing {$count} items");
Log::debug("Processing item: " . json_encode($item));
```

## Testing Patterns

### 1. Feature Tests
**WRITE**: Comprehensive feature tests:

```php
// ✅ CORRECT - Feature test structure
it('translates strings successfully', function () {
    $strings = ['Hello', 'World'];
    
    $translator = AITranslator::configure()
        ->from('en')
        ->to('pt_BR');
    
    $result = $translator->run($strings);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2);
});
```

## File Organization

### 1. Directory Structure
```
src/
├── Console/Commands/     # Artisan commands
├── FinderPatterns/      # Pattern classes for string finding
├── Providers/           # Service providers and AI providers
├── Services/           # Main business logic services
├── helpers.php         # Global helper functions
├── Langfy.php         # Main package class
├── LangfyServiceProvider.php  # Laravel service provider
└── Trans.php          # Translation utilities
```

### 2. Naming Conventions
- **Classes**: PascalCase (e.g., `AITranslator`, `FinderCommand`)
- **Methods**: camelCase (e.g., `findStringsInContent`, `translateChunk`)
- **Properties**: camelCase (e.g., `$fromLanguage`, `$maxRetries`)
- **Constants**: SCREAMING_SNAKE_CASE
- **Config keys**: snake_case (e.g., `from_language`, `api_key`)

## Performance Considerations

### 1. Chunking Strategy
**IMPLEMENT**: Proper chunking for large datasets:

```php
// ✅ CORRECT - Process in chunks
collect($strings)
    ->chunk($this->chunkSize)
    ->each(function (Collection $chunk) use (&$results): void {
        $chunkResults = $this->processChunk($chunk);
        $results = $results->merge($chunkResults);
    });
```

### 2. Memory Management
**CONSIDER**: Memory usage for large operations:

```php
// ✅ CORRECT - Use generators for large datasets
protected function processLargeDataset(): Generator
{
    foreach ($this->getLargeDataset() as $item) {
        if ($this->shouldProcess($item)) {
            yield $this->processItem($item);
        }
    }
}
```

## Security Considerations

### 1. API Key Management
**SECURE**: Proper API key handling:

```php
// ✅ CORRECT - Use environment variables
$apiKey = config()->string('langfy.ai.api_key');

// ✅ CORRECT - Dynamic configuration
config(["prism.providers.{$provider}.api_key" => $apiKey]);
```

### 2. Input Validation
**VALIDATE**: All inputs properly:

```php
// ✅ CORRECT - Validate inputs
public function translate(array $strings): array
{
    if (blank($strings)) {
        return [];
    }
    
    // Normalize and validate strings
    $strings = Langfy::normalizeStringsArray($strings);
    
    return $this->processTranslation($strings);
}
```

## Documentation Standards

### 1. PHPDoc Requirements
**DOCUMENT**: All public methods, Only on parameters that are not self-explanatory

```php
/**
 * @param array<string, <string, string>> $extraData Additional data to include in the translation request.
 */
public static function exampleMethod($extraData): void
{
    // Implementation
}
```

### 2. Inline Comments
**EXPLAIN**: Complex logic with clear comments:

```php
// Remove single-line comments but keep docblocks
$content = preg_replace('/\/\/.*$/m', '', $content);

// Normalize line breaks and spaces around property declarations
$content = preg_replace('/\s*\n\s*/', ' ', (string) $content);
```

## Documentation Standards

### 1. VitePress Documentation
The project's documentation is built using **VitePress**. All documentation files are written in Markdown (`.md`) and are located in the `docs` directory.

- **Structure**: The sidebar, navigation, and other site-level configurations are managed in `docs/.vitepress/config.mts`.
- **Content**: Documentation pages are individual Markdown files. Use standard Markdown syntax, along with VitePress-specific features like custom containers and components when necessary.
- **Writing Style**: The tone should be clear, concise, and developer-focused. Explain concepts simply and provide practical code examples. Use headings, lists, and code blocks to structure the content for readability.
- **Updating**: When adding new features or changing existing ones, ensure the corresponding documentation is updated. This includes API references, command descriptions, and configuration options.

## Summary

When working on the Langfy package, always remember:


1. **Use Laravel helpers**: `filled()`, `blank()`, `collect()`, `optional()`
2. **Implement early returns**: Clean, readable code flow
3. **Use Collections**: For all array manipulation
4. **Write in English**: All code, comments, and documentation
5. **Leverage Laravel features**: Container, facades, configuration, views
6. **Follow fluent interfaces**: Chain methods for better UX
7. **Handle errors gracefully**: Proper exception handling and logging
8. **Type everything**: Strict typing throughout
9. **Test comprehensively**: Feature and unit tests
10. **Document thoroughly**: PHPDoc and inline comments

This package is a modern Laravel package that showcases best practices in PHP 8.2+, Laravel 10-12, and AI integration. Always maintain these standards when contributing to or extending the codebase.
