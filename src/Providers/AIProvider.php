<?php

declare(strict_types = 1);

namespace Langfy\Providers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Throwable;

class AIProvider
{
    protected const QUOTE_PLACEHOLDER = '@@QUOTE@@';

    public function __construct(
        protected string $fromLanguage,
        protected string $toLanguage,
        protected string $aiModel,
        protected float $temperature,
        protected Provider | string $modelProvider,
        protected int $maxRetries = 3,
        protected int $retryDelay = 2,
    ) {
        $this->setApiKey();
    }

    public function translate(array $strings): array
    {
        // Replace quotes with placeholders in keys for schema compatibility
        $processedStrings = $this->replaceQuotesInKeys($strings);

        $prompt = $this->buildPrompt($processedStrings);
        $schema = $this->buildSchema($processedStrings);

        $translations = $this->executeWithRetry($prompt, $schema, $processedStrings);

        // Restore original keys in the result
        return $this->restoreOriginalKeys($translations, $strings);
    }

    protected function executeWithRetry(string $prompt, ObjectSchema $schema, array $strings): array
    {
        $attempt       = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                $response = Prism::structured()
                    ->using($this->modelProvider, $this->aiModel)
                    ->withSystemPrompt($this->getSystemPrompt())
                    ->usingTemperature($this->temperature)
                    ->withSchema($schema)
                    ->withPrompt($prompt)
                    ->asStructured();

                return $this->extractTranslations($response);
            } catch (Throwable $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelay * $attempt);
                }
            }
        }

        Log::error("Translation failed after {$this->maxRetries} attempts: " . $lastException->getMessage());

        return [];
    }

    protected function buildPrompt(array $strings): string
    {
        $stringsList = collect($strings)
            ->map(fn ($value, $key): string => "{$key}: \"{$value}\"")
            ->values()
            ->implode("\n");

        return "Translate the following strings from {$this->fromLanguage} to {$this->toLanguage}. " .
            "Keep the original format and preserve any HTML or placeholders. " .
            "Note: " . self::QUOTE_PLACEHOLDER . " represents double quotes in the original text.\n\n" .
            $stringsList;
    }

    protected function buildSchema(array $strings): ObjectSchema
    {
        return new ObjectSchema(
            name: 'Translations',
            description: 'Translations for the provided strings',
            properties: array_map(
                fn ($key): StringSchema => new StringSchema(
                    $key,
                    "Translation to {$this->toLanguage}"
                ),
                array_keys($strings)
            ),
            requiredFields: array_keys($strings)
        );
    }

    protected function extractTranslations($response): array
    {
        $translations = [];

        if (isset($response->structured['properties'])) {
            $properties = $response->structured['properties'];

            foreach ($properties as $originalString => $propertyData) {
                if (isset($propertyData['value'])) {
                    $translations[$originalString] = $propertyData['value'];
                }
            }

            return $translations;
        }

        $properties = $response->structured;

        foreach ($properties as $originalString => $translatedString) {
            if (is_string($translatedString)) {
                $translations[$originalString] = $translatedString;
            }
        }

        return $translations;
    }

    protected function getSystemPrompt(): string
    {
        $context = config('langfy.context');

        if ($context instanceof View) {
            $context = $context->render();
        }

        return view('langfy::system-prompt', ['context' => $context])->render();
    }

    protected function setApiKey(): void
    {
        $apiKey   = config()->string('langfy.ai.api_key');
        $provider = $this->modelProvider instanceof Provider ? $this->modelProvider->value : $this->modelProvider;

        config(["prism.providers.{$provider}.api_key" => $apiKey]);
    }

    /**
     * Replace double quotes in array keys with placeholders to avoid JSON Schema issues
     */
    protected function replaceQuotesInKeys(array $strings): array
    {
        $processedStrings = [];

        foreach ($strings as $key => $value) {
            $processedKey = str_replace('"', self::QUOTE_PLACEHOLDER, $key);
            $processedStrings[$processedKey] = $value;
        }

        return $processedStrings;
    }

    /**
     * Restore original keys with quotes from the processed results
     */
    protected function restoreOriginalKeys(array $translations, array $originalStrings): array
    {
        $restoredTranslations = [];

        // Create a mapping from processed keys back to original keys
        $keyMapping = [];
        foreach ($originalStrings as $originalKey => $value) {
            $processedKey = str_replace('"', self::QUOTE_PLACEHOLDER, $originalKey);
            $keyMapping[$processedKey] = $originalKey;
        }

        // Restore the original keys in translations
        foreach ($translations as $processedKey => $translation) {
            $originalKey = $keyMapping[$processedKey] ?? str_replace(self::QUOTE_PLACEHOLDER, '"', $processedKey);

            $restoredTranslation = str_replace(self::QUOTE_PLACEHOLDER, '"', $translation);
            $restoredTranslations[$originalKey] = $restoredTranslation;
        }

        return $restoredTranslations;
    }
}
