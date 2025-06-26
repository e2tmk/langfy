<?php

declare(strict_types = 1);

namespace Langfy\Services;

use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Langfy\Concerns\HasProgressCallbacks;
use Langfy\Langfy;
use Langfy\Providers\AIProvider;
use Prism\Prism\Enums\Provider;

class AITranslator
{
    use HasProgressCallbacks;

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
    ) {}

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

        $translations    = collect();
        $chunks          = collect($this->strings)->chunk($this->chunkSize);
        $totalChunks     = $chunks->count();
        $processedChunks = 0;

        $chunks->each(function (Collection $chunk) use (&$translations, &$processedChunks, $totalChunks): void {
            $chunkTranslations = $this->translateChunk($chunk);
            $translations      = $translations->merge($chunkTranslations);

            $processedChunks++;
            $this->callProgressCallback($processedChunks, $totalChunks, extraData: [
                'language' => $this->toLanguage,
            ]);
        });

        return $this->ensureAllStringsTranslated($translations->toArray());
    }

    /**
     * Run translator with configured options
     *
     * @return array<string, array<string, string>> Associative array where keys are language codes and values are arrays of translated strings, or a single array of translated strings if only one target language is specified.
     */
    public static function quickTranslate(array $strings, string | array | null $toLanguages = null, ?string $fromLanguage = null): array
    {
        $singleToLanguage  = is_string($toLanguages);
        $translatedStrings = collect();

        if (blank($strings)) {
            return $translatedStrings->toArray();
        }

        if (is_string($toLanguages)) {
            $toLanguages = [$toLanguages];
        }

        if (blank($toLanguages)) {
            $toLanguages = config()->array('langfy.to_language', []);
        }

        if (blank($fromLanguage)) {
            $fromLanguage = config('langfy.from_language', 'en');
        }

        collect($toLanguages)
            ->filter(fn ($language): bool => is_string($language) && filled($language))
            ->each(function (string $language) use ($strings, &$translatedStrings, $fromLanguage): void {
                $result = self::configure()
                    ->from($fromLanguage)
                    ->to($language)
                    ->run($strings);

                $translatedStrings->put($language, $result);
            });

        // If only one target language is specified, return the first translated string
        if ($singleToLanguage && $translatedStrings->isNotEmpty()) {
            return $translatedStrings->first();
        }

        return $translatedStrings->toArray();
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
