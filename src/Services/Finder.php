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

    protected FunctionPattern $functionPattern;

    protected PropertyPattern $propertyPattern;

    protected VariablePattern $variablePattern;

    public function __construct()
    {
        $this->paths              = collect([]);
        $this->defaultIgnorePaths = array_merge($this->defaultIgnorePaths, config('langfy.finder.ignore_paths', []));

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

        $this->defaultIgnorePaths = array_merge($this->defaultIgnorePaths, $paths);

        return $this;
    }

    public function ignoreExtensions(string | array $extensions): self
    {
        if (is_string($extensions)) {
            $extensions = [$extensions];
        }

        $this->defaultIgnoreExtensions = array_merge($this->defaultIgnoreExtensions, $extensions);

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
                ->notPath($this->defaultIgnorePaths)
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
                ->notPath($this->defaultIgnorePaths)
                ->name(['*.php', '*.blade.php']);

            foreach ($finder as $file) {
                $content = file_get_contents($file->getRealPath());
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

        if (filled($fileExtension) && in_array($fileExtension, $this->defaultIgnoreExtensions)) {
            return $strings;
        }

        $strings = array_merge($strings, $this->findFunctionStrings($content));
        $strings = array_merge($strings, $this->findPropertyStrings($content));
        $strings = array_merge($strings, $this->findVariableStrings($content));

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
                // Skip empty strings or obvious non-translatable values
                if (strlen($match) < 2) {
                    continue;
                }

                if ($this->shouldSkipString($match)) {
                    continue;
                }

                $strings[] = $match;
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
}
