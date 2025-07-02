# Langfy Laravel Package - AI Agent Guidelines

## 1. Project Overview

**Langfy** is a powerful Laravel package designed to streamline the translation workflow. It combines intelligent string discovery with AI-powered translation services to automate the internationalization (i18n) process.

- **Name**: e2tmk/langfy
- **Type**: Laravel Package
- **PHP Version**: ^8.2
- **Laravel Support**: 10.x, 11.x, 12.x
- **Main Dependencies**: Spatie Laravel Package Tools, Prism PHP, Nwidart/laravel-modules.

## 2. Getting Started

### Installation and Setup

1.  **Install dependencies**:
    ```bash
    composer require e2tmk/langfy --dev
    ```
2.  **Publish configuration**: This creates `config/langfy.php`.
    ```bash
    php artisan vendor:publish --tag="langfy-config"
    ```
3.  **Set up environment**: Add to your `.env` file.
    ```env
    LANGFY_AI_API_KEY=sk-your-openai-api-key-here
    LANGFY_AI_MODEL=gpt-4o-mini
    ```

### Basic Commands

-   **Find translatable strings**: Scans the application and/or modules for strings to translate.
    ```bash
    # Interactive mode
    php artisan langfy:finder

    # Process only the main application
    php artisan langfy:finder --app
    ```
-   **Translate strings**: Translates the strings found in your language files.
    ```bash
    # Interactive mode
    php artisan langfy:trans

    # Translate the app to specific languages
    php artisan langfy:trans --app --to=pt_BR,es_ES
    ```

## 3. Core Architecture

The package is built around two primary services, orchestrated by the main `Langfy` class.

-   **`Langfy\Langfy`**: The main entry point, providing a fluent interface to chain finding, saving, and translation operations.
-   **`Langfy\Services\Finder`**: Locates translatable strings in PHP and Blade files using pattern matching (`FunctionPattern`, `PropertyPattern`, `VariablePattern`). It's highly configurable.
-   **`Langfy\Services\AITranslator`**: Translates strings using AI providers. It features automatic chunking, retry logic, and parallel processing via a process pool for performance.
-   **`Langfy\Providers\AIProvider`**: A wrapper around `Prism PHP` that handles direct communication with AI services, builds schemas for structured responses, and manages API keys.

## 4. Development Guidelines

### Coding Standards & Patterns

**CRITICAL**: Adhere to these standards to maintain code quality and consistency.

1.  **Use Laravel Helpers**: Always prefer Laravel helpers over native PHP functions.
    - `filled()` instead of `!empty()`
    - `blank()` instead of `empty()`
    - `optional()` for safe property access
    - `collect()` for all array manipulation. **All array manipulation MUST use Laravel Collections.**

2.  **Early Return Pattern**: Use early returns to reduce nesting and improve readability.

    ```php
    // ✅ CORRECT
    public function process(array $items): void
    {
        if (blank($items)) {
            return;
        }

        // ... main logic
    }
    ```

3.  **Language**: All code, comments, and documentation **MUST** be in English.

4.  **Strict Typing**:
    - Always start files with `declare(strict_types = 1);`.
    - Type all properties, parameters, and return values. Use union types where appropriate.

### Code Style (PSR-12)

This project follows the **PSR-12** coding standard.

-   **Automatic Formatting**: Use `pint` to automatically format your code before committing.
    ```bash
    ./vendor/bin/pint
    ```
-   The configuration for `pint` is in `pint.json`.

### Laravel-Specific Practices

-   **Dependency Injection**: Use Laravel's service container for resolving classes (`app()`, constructor injection).
-   **Configuration**: Use the `config()` helper to access configuration values from `config/langfy.php`.
-   **Facades**: Use facades for core Laravel services like `File`, `Log`, and `Process`.
-   **Fluent Interface**: When adding new features to services like `Finder` or `AITranslator`, maintain the fluent, chainable interface.

### Performance & Security

-   **Chunking**: When processing large arrays of strings, always use chunking (`collect($items)->chunk(...)`) to manage memory.
-   **API Keys**: Never hardcode API keys. Always load them from the environment via the configuration file.
-   **Input Validation**: Sanitize and validate any input, especially before passing it to the AI provider.

## 5. Testing

The project uses **Pest** for testing. Tests are located in the `tests/` directory.

### Running Tests

-   **Run all tests**:
    ```bash
    composer test
    ```
    or
    ```bash
    ./vendor/bin/pest
    ```

-   **Run a single test file**:
    ```bash
    ./vendor/bin/pest tests/Features/Services/FinderTest.php
    ```

### Writing Tests

-   Write **Feature tests** for all new functionality.
-   Focus on real-world use cases and integration between different parts of the package.
-   Use the helpers in `tests/Pest.php` and the base `tests/TestCase.php` for setup.
-   Use the `tests/cache` directory for temporary file generation during tests.

## 6. Documentation

The documentation is built with **VitePress** and is located in the `docs/` directory.

### Running the Documentation Site Locally

1.  **Navigate to the docs directory**:
    ```bash
    cd docs
    ```
2.  **Install dependencies**:
    ```bash
    npm install
    ```
3.  **Start the dev server**:
    ```bash
    npm run docs:dev
    ```
    The site will be available at `http://localhost:5173`.

### Updating Documentation

-   **File Structure**: Site configuration is in `docs/.vitepress/config.mts`. Content pages are Markdown files (`.md`) in `docs/`.
-   **Content**: When adding or changing features, update the corresponding documentation. This includes API methods, command options, and configuration settings.
-   **Style**: Write clear, developer-focused content with practical code examples.

## 7. Artisan Commands

-   `langfy:finder`: Finds and saves translatable strings.
    - `--app`: Target the main application.
    - `--modules=User,Blog`: Target specific modules.
    - `--trans`: Automatically translate found strings.
    - `--queue`: Use the queue for translations.
-   `langfy:trans`: Translates existing language files.
    - `--to=pt_BR,es_ES`: Specify target languages.
    - Supports `--app`, `--modules`, and `--queue` like the finder.
-   `langfy:translate-chunk`: Internal command used by the `AITranslator`'s process pool for parallel processing. Not intended for direct use.

## 8. Summary Checklist

Before committing code, ensure you have:

1.  Followed all **coding standards** and **Laravel best practices**.
2.  Formatted your code with `./vendor/bin/pint`.
3.  Written **feature tests** for any new functionality.
4.  Ensured all **tests are passing**.
5.  Updated the **documentation** in the `docs/` directory.
