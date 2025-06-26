<?php

declare(strict_types = 1);

namespace Langfy;

use Langfy\Enums\Context;
use Langfy\Helpers\Utils;
use Langfy\Services\AITranslator;
use Langfy\Services\Finder;

class Langfy
{
    protected bool $enableFinder = false;

    protected bool $enableSave = false;

    protected bool $enableTranslate = false;

    protected string | array | null $translateTo = null;

    protected array $foundStrings = [];

    protected array $paths = [];

    protected ?\Closure $finderProgressCallback = null;

    protected ?\Closure $translateProgressCallback = null;

    protected Utils $utils;

    protected function __construct(protected Context $context, protected ?string $moduleName = null)
    {
        $this->setupDefaultPaths();
        $this->utils = new Utils();
    }

    public static function for(Context $context, ?string $moduleName = null): self
    {
        return new self($context, $moduleName);
    }

    /** Enable finder functionality. */
    public function finder(bool $enabled = true): self
    {
        $this->enableFinder = $enabled;

        return $this;
    }

    /** Enable finder save functionality. */
    public function save(bool $enabled = true): self
    {
        $this->enableSave = $enabled;

        return $this;
    }

    /** Enable translate functionality. */
    public function translate(bool $enabled = true, string | array | null $to = null): self
    {
        $this->enableTranslate = $enabled;

        if (filled($to)) {
            $this->translateTo = $to;
        }

        return $this;
    }

    /** Set progress callback for finder operations. */
    public function onFinderProgress(\Closure $callback): self
    {
        $this->finderProgressCallback = $callback;

        return $this;
    }

    /** Set progress callback for translate operations. */
    public function onTranslateProgress(\Closure $callback): self
    {
        $this->translateProgressCallback = $callback;

        return $this;
    }

    /** Get found strings without performing other operations. */
    public function getStrings(): array
    {
        if (! $this->enableFinder) {
            $this->finder(true);
        }

        $this->runFinder();

        return $this->getNewStrings();
    }

    /** Perform all configured operations. */
    public function perform(): array
    {
        $results = [];

        if ($this->enableFinder) {
            $this->runFinder();
            $results['found_strings'] = count($this->foundStrings);
        }

        if ($this->enableSave && filled($this->foundStrings)) {
            $this->runSave();
            $results['saved'] = true;
        }

        if ($this->enableTranslate) {
            $stringsToTranslate = $this->getStringsForTranslation();

            if (filled($stringsToTranslate)) {
                $translations            = $this->runTranslate($stringsToTranslate);
                $results['translations'] = $translations;
            }
        }

        return $results;
    }

    /** Set up default paths based on context. */
    protected function setupDefaultPaths(): void
    {
        if ($this->context === Context::Application) {
            $this->paths = config()->array('langfy.finder.application_paths', self::utils()->getDefaultApplicationPaths());

            return;
        }

        $this->paths = [self::utils()->modulePath($this->moduleName)];
    }

    /** Run finder */
    protected function runFinder(): void
    {
        if (blank($this->paths)) {
            return;
        }

        $finder = Finder::in($this->paths)
            ->ignore(['vendor', 'node_modules', 'storage', 'lang']);

        if (filled($this->finderProgressCallback)) {
            $finder->onProgress($this->finderProgressCallback);
        }

        $this->foundStrings = $finder->run();
    }

    /** Run save finder operation. */
    protected function runSave(): void
    {
        $filePath = $this->getLanguageFilePath();
        self::utils()->saveStringsToFile($this->foundStrings, $filePath);
    }

    /** Run the translation operation. */
    protected function runTranslate(array $strings): array
    {
        if (blank($strings)) {
            return [];
        }

        $fromLanguage = config('langfy.from_language', 'en');
        $toLanguages  = $this->getTargetLanguages();

        return collect($toLanguages)
            ->mapWithKeys(function (string $toLanguage) use ($fromLanguage, $strings): array {
                // Filter strings that are already translated for this specific language
                $stringsForThisLanguage = $this->getUntranslatedStringsForLanguage($strings, $toLanguage);

                if (blank($stringsForThisLanguage)) {
                    return [$toLanguage => []];
                }

                $translator = AITranslator::configure()
                    ->from($fromLanguage)
                    ->to($toLanguage);

                if (filled($this->translateProgressCallback)) {
                    $translator->onProgress($this->translateProgressCallback);
                }

                $translations = $translator->run($stringsForThisLanguage);

                if (filled($translations)) {
                    $this->saveTranslations($translations, $toLanguage);
                }

                return [$toLanguage => $translations];
            })->toArray();
    }

    /** Get untranslated strings for a specific target language. */
    protected function getUntranslatedStringsForLanguage(array $strings, string $targetLanguage): array
    {
        $toFilePath = $this->getLanguageFilePath($targetLanguage);

        // Get existing translations for this language
        $existingTranslations = [];

        if (file_exists($toFilePath)) {
            $existingTranslations = rescue(
                fn (): mixed => json_decode(file_get_contents($toFilePath), true),
                []
            ) ?? [];
        }

        // Return only strings that don't exist in the target language file
        return collect($strings)
            ->reject(fn ($value, $key): bool => array_key_exists($key, $existingTranslations))
            ->toArray();
    }

    /** Get the language file path. */
    protected function getLanguageFilePath(?string $language = null): string
    {
        $language ??= config('langfy.from_language', 'en');

        if ($this->context === Context::Application) {
            return lang_path() . '/' . $language . '.json';
        }

        if ($this->context === Context::Module && filled($this->moduleName)) {
            $modulePath = self::utils()->modulePath($this->moduleName);

            return $modulePath . '/lang/' . $language . '.json';
        }

        return lang_path() . '/' . $language . '.json';
    }

    /** Get target languages for translation. */
    protected function getTargetLanguages(): array
    {
        if (filled($this->translateTo)) {
            return is_array($this->translateTo) ? $this->translateTo : [$this->translateTo];
        }

        return config()->array('langfy.to_languages');
    }

    /** Get new strings that don't exist in the language file. */
    protected function getNewStrings(): array
    {
        if (blank($this->foundStrings)) {
            return [];
        }

        $filePath = $this->getLanguageFilePath();

        if (! file_exists($filePath)) {
            return $this->foundStrings;
        }

        $existingStrings = json_decode(file_get_contents($filePath), true) ?? [];

        return collect($this->foundStrings)
            ->reject(fn ($value, $key): bool => array_key_exists($key, $existingStrings))
            ->toArray();
    }

    /** Get strings for translation (existing in from language but missing in to language). */
    protected function getStringsForTranslation(): array
    {
        $fromLanguage = config('langfy.from_language', 'en');
        $fromFilePath = $this->getLanguageFilePath($fromLanguage);

        if (! file_exists($fromFilePath)) {
            return [];
        }

        $fromStrings = rescue(fn (): mixed => json_decode(file_get_contents($fromFilePath), true), []) ?? [];

        if (blank($fromStrings)) {
            return [];
        }

        // If we have found strings and save is enabled, we want to translate those
        if ($this->enableSave && filled($this->foundStrings)) {
            return $this->filterUntranslatedStrings($this->foundStrings);
        }

        // Otherwise, find strings that need translation
        return $this->filterUntranslatedStrings($fromStrings);
    }

    /** Filter strings that haven't been translated yet in any target language. */
    protected function filterUntranslatedStrings(array $strings): array
    {
        $stringsNeedingTranslation = collect();

        collect($this->getTargetLanguages())
            ->each(function (string $toLanguage) use ($strings, &$stringsNeedingTranslation): void {
                $toFilePath = $this->getLanguageFilePath($toLanguage);

                $existingTranslations = [];

                if (file_exists($toFilePath)) {
                    $existingTranslations = rescue(
                        fn (): mixed => json_decode(file_get_contents($toFilePath), true),
                        []
                    ) ?? [];
                }

                // Find strings that don't exist in this target language
                $missingStrings = collect($strings)
                    ->reject(fn ($value, $key): bool => array_key_exists($key, $existingTranslations))
                    ->toArray();

                $stringsNeedingTranslation = $stringsNeedingTranslation->merge($missingStrings);
            });

        return $stringsNeedingTranslation->unique()->toArray();
    }

    /** Save translations to file. */
    protected function saveTranslations(array $translations, string $language): void
    {
        $filePath = $this->getLanguageFilePath($language);
        self::utils()->saveStringsToFile($translations, $filePath);
    }

    public static function utils(): Utils
    {
        return new Utils();
    }
}
