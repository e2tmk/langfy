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

    protected Utils $utils;

    protected function __construct(protected Context $context, protected ?string $moduleName = null)
    {
        $this->setupDefaultPaths();
        $this->utils = new Utils();
    }

    public static function for(Context $context, ?string $moduleName): self
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
            $this->paths = config()->array('langfy.finder.application_paths', $this->utils->getDefaultApplicationPaths());

            return;
        }

        $this->paths = [$this->utils->modulePath($this->moduleName)];
    }

    /** Run finder */
    protected function runFinder(): void
    {
        if (blank($this->paths)) {
            return;
        }

        $this->foundStrings = Finder::in($this->paths)
            ->ignore(['vendor', 'node_modules', 'storage', 'lang'])
            ->run();
    }

    /** Run save finder operation. */
    protected function runSave(): void
    {
        $filePath = $this->getLanguageFilePath();
        $this->utils->saveStringsToFile($this->foundStrings, $filePath);
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
                $translator = AITranslator::configure()
                    ->from($fromLanguage)
                    ->to($toLanguage);

                $translations = $translator->run($strings);

                if (filled($translations)) {
                    $this->saveTranslations($translations, $toLanguage);
                }

                return [$toLanguage => $translations];
            })->toArray();
    }

    /** Get the language file path. */
    protected function getLanguageFilePath(?string $language = null): string
    {
        $language ??= config('langfy.from_language', 'en');

        if ($this->context === Context::Application) {
            return lang_path() . '/' . $language . '.json';
        }

        if ($this->context === Context::Module && filled($this->moduleName)) {
            $modulePath = $this->utils->modulePath($this->moduleName);

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
            return $this->foundStrings;
        }

        // Otherwise, find strings that need translation using collections
        return collect($this->getTargetLanguages())
            ->flatMap(function (string $toLanguage) use ($fromStrings): array {
                $toFilePath = $this->getLanguageFilePath($toLanguage);
                $fileExists = file_exists($toFilePath);

                // If the to language file does not exist, all from strings are new
                $toStrings = rescue(
                    fn (): mixed => $fileExists ? json_decode(file_get_contents($toFilePath), true) : [],
                    []
                );

                return collect($fromStrings)
                    ->reject(fn ($value, $key): bool => array_key_exists($key, $toStrings))
                    ->toArray();
            })
            ->unique()
            ->values()
            ->toArray();
    }

    /** Save translations to file. */
    protected function saveTranslations(array $translations, string $language): void
    {
        $filePath = $this->getLanguageFilePath($language);
        $this->utils->saveStringsToFile($translations, $filePath);
    }

    public static function utils(): Utils
    {
        return new Utils();
    }
}
