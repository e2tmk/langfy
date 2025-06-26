<?php

declare(strict_types = 1);

namespace Langfy\Services;

use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Langfy\Langfy;
use Langfy\Providers\AIProvider;
use Prism\Prism\Enums\Provider;

class AITranslator
{
    protected int $chunkSize = 15;

    protected int $maxRetries = 3;

    protected int $retryDelay = 2;

    protected array $strings;

    protected string $toLanguage;

    protected ?AIProvider $aiProvider = null;

    public function __construct(
        #[Config('langfy.from_language')]
        protected string $fromLanguage,
        #[Config('langfy.ai.model')]
        protected string $aiModel,
        #[Config('langfy.ai.temperature')]
        protected float $temperature,
        #[Config('langfy.ai.provider')]
        protected Provider | string $modelProvider,
    ) {
    }

    public static function configure(): AITranslator
    {
        return app(AITranslator::class);
    }

    public function from(string $fromLanguage): AITranslator
    {
        $this->fromLanguage = $fromLanguage;

        return $this;
    }

    public function to(string $toLanguage): AITranslator
    {
        $this->toLanguage = $toLanguage;

        return $this;
    }

    public function model(string $model): AITranslator
    {
        $this->aiModel = $model;

        return $this;
    }

    public function temperature(float $temperature): AITranslator
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function provider(Provider | string $provider): AITranslator
    {
        $this->modelProvider = $provider;

        return $this;
    }

    public function chunkSize(int $chunkSize): AITranslator
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    public function run(array $strings): array
    {
        if (blank($strings)) {
            return [];
        }

        $this->strings = Langfy::normalizeStringsArray($strings);

        $translations = collect();

        collect($this->strings)
            ->chunk($this->chunkSize)
            ->each(function (Collection $chunk) use (&$translations): void {
                $chunkTranslations = $this->translateChunk($chunk);
                $translations      = $translations->merge($chunkTranslations);
            });

        return $this->ensureAllStringsTranslated($translations->toArray());
    }

    protected function translateChunk(Collection $chunk): array
    {
        return $this->getAIProvider()->translate($chunk->toArray());
    }

    protected function getAIProvider(): AIProvider
    {
        return $this->aiProvider ??= app(AIProvider::class, [
            'fromLanguage'  => $this->fromLanguage,
            'toLanguage'    => $this->toLanguage,
            'aiModel'       => $this->aiModel,
            'temperature'   => $this->temperature,
            'modelProvider' => $this->modelProvider,
            'maxRetries'    => $this->maxRetries,
            'retryDelay'    => $this->retryDelay,
        ]);
    }

    protected function ensureAllStringsTranslated(array $translations): array
    {
        $sourceKeys     = array_keys($this->strings);
        $translatedKeys = array_keys($translations);
        $missingKeys    = array_diff($sourceKeys, $translatedKeys);

        if (blank($missingKeys)) {
            return $translations;
        }

        $retryCount = 0;
        $maxRetries = 3;

        while (filled($missingKeys) && $retryCount < $maxRetries) {
            $stringsToTranslate = collect($missingKeys)
                ->mapWithKeys(fn ($key) => [$key => $this->strings[$key]])
                ->toArray();

            $missingTranslations = collect($stringsToTranslate)
                ->chunk($this->chunkSize)
                ->flatMap(fn (Collection $chunk): array => $this->translateChunk($chunk))
                ->toArray();

            // If no translations were returned, we assume the API is not responding correctly
            if (blank($missingTranslations)) {
                $retryCount++;

                $this->sleepBetweenRetries($missingKeys, $retryCount, $maxRetries);

                continue;
            }

            $translations = array_merge($translations, $missingTranslations);

            // Recalculate missing keys and determine if we made progress
            $translatedKeys       = array_keys($translations);
            $previousMissingCount = count($missingKeys);
            $missingKeys          = array_diff($sourceKeys, $translatedKeys);
            $newMissingCount      = count($missingKeys);

            // Reset retry count if we made progress, otherwise increment
            $retryCount = ($newMissingCount < $previousMissingCount) ? 0 : $retryCount + 1;

            $this->sleepBetweenRetries($missingKeys, $retryCount, $maxRetries);
        }

        if (filled($missingKeys)) {
            Log::error("Failed to translate the following keys after {$retryCount} retries: " . implode(', ', $missingKeys));
        }

        return $translations;
    }

    private function sleepBetweenRetries(array $missingKeys, int $retryCount, int $maxRetries): void
    {
        if (filled($missingKeys) && $retryCount < $maxRetries) {
            sleep($this->retryDelay);
        }
    }
}
