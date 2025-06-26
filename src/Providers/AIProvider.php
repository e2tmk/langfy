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
        $prompt = $this->buildPrompt($strings);
        $schema = $this->buildSchema($strings);

        return $this->executeWithRetry($prompt, $schema, $strings);
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
            "Keep the original format and preserve any HTML or placeholders.\n\n" .
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
                    "Translation for '{$strings[$key]}' in {$this->toLanguage} language"
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
}
