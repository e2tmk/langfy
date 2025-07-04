<?php

declare(strict_types = 1);

namespace Langfy\Services;

use Illuminate\Support\Collection;
use Langfy\Concerns\HasProgressCallbacks;
use Langfy\FinderPatterns\FunctionPattern;
use Langfy\FinderPatterns\PropertyPattern;
use Langfy\FinderPatterns\VariablePattern;
use Symfony\Component\Finder\Finder as SymfonyFinder;

class Finder
{
    use HasProgressCallbacks;

    protected Collection $paths;

    protected array $defaultIgnorePaths = [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap',
        'public',
        'lang',
        'bootstrap/cache',
    ];

    protected array $defaultIgnoreExtensions = [
        'json',
        'md',
        'txt',
        'log',
    ];

    protected array $ignoreFiles = [];
    protected array $ignorePaths = [];
    protected array $ignoreNamespaces = [];
    protected array $ignoreStrings = [];
    protected array $ignorePatterns = [];
    protected array $ignoreExtensions = [];

    protected FunctionPattern $functionPattern;

    protected PropertyPattern $propertyPattern;

    protected VariablePattern $variablePattern;

    public function __construct()
    {
        $this->paths = collect([]);

        // Load ignore configuration from new structure
        $ignoreConfig = config('langfy.finder.ignore', []);

        $this->ignoreFiles = $ignoreConfig['files'] ?? [];
        $this->ignorePaths = array_merge($this->defaultIgnorePaths, $ignoreConfig['paths'] ?? []);
        $this->ignoreNamespaces = $ignoreConfig['namespaces'] ?? [];
        $this->ignoreStrings = $ignoreConfig['strings'] ?? [];
        $this->ignorePatterns = $ignoreConfig['patterns'] ?? [];
        $this->ignoreExtensions = array_merge($this->defaultIgnoreExtensions, $ignoreConfig['extensions'] ?? []);

        $this->functionPattern = new FunctionPattern();
        $this->propertyPattern = new PropertyPattern();
        $this->variablePattern = new VariablePattern();
    }

    public static function in(string | array $paths): self
    {
        $instance = new self();

        if (is_string($paths)) {
            $paths = [$paths];
        }

        $instance->paths = collect($paths);

        return $instance;
    }

    public function and(string | array $paths): self
    {
        if (is_string($paths)) {
            $paths = [$paths];
        }

        $this->paths = $this->paths->merge($paths);

        return $this;
    }

    public function ignore(string | array $paths): self
    {
        if (is_string($paths)) {
            $paths = [$paths];
        }

        $this->ignorePaths = array_merge($this->ignorePaths, $paths);

        return $this;
    }

    public function ignoreExtensions(string | array $extensions): self
    {
        if (is_string($extensions)) {
            $extensions = [$extensions];
        }

        $this->ignoreExtensions = array_merge($this->ignoreExtensions, $extensions);

        return $this;
    }

    public function ignoreFiles(string | array $files): self
    {
        if (is_string($files)) {
            $files = [$files];
        }

        $this->ignoreFiles = array_merge($this->ignoreFiles, $files);

        return $this;
    }

    public function ignoreNamespaces(string | array $namespaces): self
    {
        if (is_string($namespaces)) {
            $namespaces = [$namespaces];
        }

        $this->ignoreNamespaces = array_merge($this->ignoreNamespaces, $namespaces);

        return $this;
    }

    public function ignoreStrings(string | array $strings): self
    {
        if (is_string($strings)) {
            $strings = [$strings];
        }

        $this->ignoreStrings = array_merge($this->ignoreStrings, $strings);

        return $this;
    }

    public function ignorePatterns(string | array $patterns): self
    {
        if (is_string($patterns)) {
            $patterns = [$patterns];
        }

        $this->ignorePatterns = array_merge($this->ignorePatterns, $patterns);

        return $this;
    }

    /**
     * @return array<string>
     */
    public function run(): array
    {
        $results        = [];
        $totalFiles     = 0;
        $processedFiles = 0;

        // First pass: count total files for progress tracking
        foreach ($this->paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $finder = (new SymfonyFinder())
                ->files()
                ->in($path)
                ->notPath($this->ignorePaths)
                ->name(['*.php', '*.blade.php']);

            $totalFiles += iterator_count($finder);
        }

        // Second pass: process files with progress tracking
        foreach ($this->paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $finder = (new SymfonyFinder())
                ->files()
                ->in($path)
                ->notPath($this->ignorePaths)
                ->name(['*.php', '*.blade.php']);

            foreach ($finder as $file) {
                // Check if file should be ignored by filename
                if ($this->shouldIgnoreFile($file->getFilename())) {
                    continue;
                }

                $content = file_get_contents($file->getRealPath());

                // Check if file should be ignored by namespace
                if ($this->shouldIgnoreByNamespace($content)) {
                    continue;
                }

                $strings = $this->findStringsInContent($content, $file->getExtension());

                $results = array_merge($results, $strings);

                $processedFiles++;
                $this->callProgressCallback($processedFiles, $totalFiles, extraData: [
                    'file' => $file->getRelativePathname(),
                    'path' => $path,
                ]);
            }
        }

        return array_unique($results);
    }

    public function findStringsInContent(string $content, ?string $fileExtension = null): array
    {
        $strings = [];

        if (filled($fileExtension) && in_array($fileExtension, $this->ignoreExtensions)) {
            return $strings;
        }

        $strings = array_merge($strings, $this->findFunctionStrings($content));
        $strings = array_merge($strings, $this->findPropertyStrings($content));
        $strings = array_merge($strings, $this->findVariableStrings($content));

        // Filter out ignored strings and patterns
        $strings = $this->filterIgnoredStrings($strings);

        return array_unique($strings);
    }

    protected function findFunctionStrings(string $content): array
    {
        $strings  = [];
        $patterns = $this->functionPattern->getPatterns();

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);

            if (blank($matches[1])) {
                continue;
            }

            foreach ($matches[1] as $match) {
                // Unescape the captured string
                $unescapedMatch = $this->unescapeString($match);

                // Skip empty strings or obvious non-translatable values
                if (strlen($unescapedMatch) < 2) {
                    continue;
                }

                if ($this->shouldSkipString($unescapedMatch)) {
                    continue;
                }

                $strings[] = $unescapedMatch;
            }
        }

        return array_unique($strings);
    }

    protected function findPropertyStrings(string $content): array
    {
        return $this->findTranslatableStringsWithPatterns($content, $this->propertyPattern->getPatterns());
    }

    protected function findVariableStrings(string $content): array
    {
        $patterns = $this->variablePattern->getPatterns();

        return $this->getMatches($content, $patterns);
    }

    protected function findTranslatableStringsWithPatterns(string $content, array $patterns): array
    {
        $normalizedContent = $this->normalizeContentForPropertyParsing($content);

        return $this->getMatches($normalizedContent, $patterns);
    }

    protected function normalizeContentForPropertyParsing(string $content): string
    {
        // Remove single-line comments but keep docblocks
        $content = preg_replace('/\/\/.*$/m', '', $content);

        // Normalize line breaks and spaces around property declarations
        $content = preg_replace('/\s*\n\s*/', ' ', (string) $content);

        // Ensure proper spacing around attributes and annotations
        $content = preg_replace('/#\[([^]]+)]\s*/', '#[$1] ', (string) $content);

        return preg_replace('/\/\*\*([^*]+)\*\/\s*/', '/**$1*/ ', (string) $content);
    }

    protected function shouldSkipString(string $string): bool
    {
        // Skip obvious non-translatable patterns
        $skipPatterns = [
            '/^\d+$/',               // Pure numbers
            '/^[a-zA-Z0-9_-]{1,3}$/', // Very short codes
            '/^\w+\.\w+$/',          // Domain-like strings
            '/^#[0-9a-fA-F]{3,6}$/', // Color codes
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $string)) {
                return true;
            }
        }

        return false;
    }

    protected function getMatches(string $content, array $patterns): array
    {
        $strings = [];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

            if ($matches === []) {
                continue;
            }

            foreach ($matches as $match) {
                if (isset($match[1]) && filled($match[1])) {
                    $string = $match[1];

                    // Skip empty strings or obvious non-translatable values
                    if (strlen($string) < 2) {
                        continue;
                    }

                    if ($this->shouldSkipString($string)) {
                        continue;
                    }

                    $strings[] = $string;
                }
            }
        }

        return array_unique($strings);
    }

    /**
     * Unescape a captured string by converting escaped quotes back to their original form.
     */
    protected function unescapeString(string $string): string
    {
        // Unescape double quotes
        $string = str_replace('\\"', '"', $string);

        // Unescape single quotes
        $string = str_replace("\\'", "'", $string);

        // Unescape backslashes (must be done last)
        $string = str_replace('\\\\', '\\', $string);

        return $string;
    }

    /**
     * Check if a file should be ignored by filename.
     */
    protected function shouldIgnoreFile(string $filename): bool
    {
        if (blank($this->ignoreFiles)) {
            return false;
        }

        return in_array($filename, $this->ignoreFiles);
    }

    /**
     * Check if a file should be ignored by namespace.
     */
    protected function shouldIgnoreByNamespace(string $content): bool
    {
        if (blank($this->ignoreNamespaces)) {
            return false;
        }

        // Extract namespace from content
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);

            foreach ($this->ignoreNamespaces as $ignoreNamespace) {
                // Check if the file namespace starts with any ignored namespace
                if (str_starts_with($namespace, $ignoreNamespace)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Filter out ignored strings and patterns from the results.
     */
    protected function filterIgnoredStrings(array $strings): array
    {
        if (blank($strings)) {
            return $strings;
        }

        return collect($strings)
            ->filter(function (string $string): bool {
                // Check if string is in ignore list
                if (filled($this->ignoreStrings) && in_array($string, $this->ignoreStrings)) {
                    return false;
                }

                // Check if string matches any ignore patterns
                if (filled($this->ignorePatterns)) {
                    foreach ($this->ignorePatterns as $pattern) {
                        // Suppress errors and check for preg_match errors
                        $result = @preg_match($pattern, $string);

                        if ($result === false) {
                            // Invalid regex pattern, skip it
                            continue;
                        }

                        if ($result === 1) {
                            return false;
                        }
                    }
                }

                return true;
            })
            ->values()
            ->toArray();
    }
}
