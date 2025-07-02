<?php

declare(strict_types = 1);

namespace Langfy\Services;

use Illuminate\Container\Attributes\Config;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
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

    protected ?\Closure $saveCallback = null;

    protected bool $useProcessPool = true;

    protected int $maxConcurrentProcesses = 5;

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

    public function withProcessPool(bool $usePool = true, int $maxConcurrent = 3): AITranslator
    {
        $this->useProcessPool         = $usePool;
        $this->maxConcurrentProcesses = $maxConcurrent;

        return $this;
    }

    public function onSave(\Closure $callback): AITranslator
    {
        $this->saveCallback = $callback;

        return $this;
    }

    public function run(array $strings): array
    {
        if (blank($strings)) {
            return [];
        }

        $this->strings = Langfy::utils()->normalizeStringsArray($strings);

        if ($this->useProcessPool) {
            return $this->runWithProcessPool();
        }

        return $this->runSequentially();
    }

    protected function runWithProcessPool(): array
    {
        $translations    = collect();
        $chunks          = collect($this->strings)->chunk($this->chunkSize);
        $totalChunks     = $chunks->count();
        $processedChunks = 0;

        // Process chunks in batches to control concurrency
        $chunkBatches = $chunks->chunk($this->maxConcurrentProcesses);

        foreach ($chunkBatches as $batch) {
            $batchTranslations = $this->processBatchWithPool($batch);
            $translations      = $translations->merge($batchTranslations);

            $processedChunks += $batch->count();
            $this->callProgressCallback($processedChunks, $totalChunks, extraData: [
                'language' => $this->toLanguage,
            ]);
        }

        return $this->ensureAllStringsTranslated($translations->toArray());
    }

    protected function processBatchWithPool(Collection $batch): Collection
    {
        $tempDir = storage_path('app/langfy/temp');
        File::ensureDirectoryExists($tempDir);

        $batchId     = Str::uuid();
        $inputFiles  = [];
        $outputFiles = [];

        // Prepare input files for each chunk
        foreach ($batch as $index => $chunk) {
            $inputFile  = "{$tempDir}/input_{$batchId}_{$index}.json";
            $outputFile = "{$tempDir}/output_{$batchId}_{$index}.json";

            file_put_contents($inputFile, json_encode($chunk->toArray()));

            $inputFiles[]  = $inputFile;
            $outputFiles[] = $outputFile;
        }

        try {
            // Create a process pool
            $pool = Process::pool(function (Pool $pool) use ($inputFiles, $outputFiles): void {
                foreach ($inputFiles as $index => $inputFile) {
                    $outputFile = $outputFiles[$index];

                    $command = sprintf(
                        'php %s langfy:translate-chunk "%s" "%s" --from="%s" --to="%s" --model="%s" --temperature="%s" --provider="%s"',
                        base_path('artisan'),
                        $inputFile,
                        $outputFile,
                        $this->fromLanguage,
                        $this->toLanguage,
                        $this->aiModel,
                        $this->temperature,
                        $this->modelProvider instanceof Provider ? $this->modelProvider->value : $this->modelProvider
                    );

                    $pool->command($command);
                }
            });

            // Execute and wait for completion
            $results = $pool->start()->wait();

            // Collect translated results
            $translations = collect();

            foreach ($outputFiles as $outputFile) {
                if (file_exists($outputFile)) {
                    $chunkTranslations = json_decode(file_get_contents($outputFile), true);

                    if ($chunkTranslations && is_array($chunkTranslations)) {
                        $translations = $translations->merge($chunkTranslations);

                        // Save chunk immediately if callback is provided
                        if (filled($this->saveCallback)) {
                            ($this->saveCallback)($chunkTranslations);
                        }
                    }
                }
            }

            return $translations;
        } finally {
            // Cleanup temp files
            foreach (array_merge($inputFiles, $outputFiles) as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    protected function runSequentially(): array
    {
        $translations    = collect();
        $chunks          = collect($this->strings)->chunk($this->chunkSize);
        $totalChunks     = $chunks->count();
        $processedChunks = 0;

        foreach ($chunks as $chunk) {
            $chunkTranslations = $this->translateChunkInternal($chunk);
            $translations      = $translations->merge($chunkTranslations);

            // Save chunk immediately if callback is provided
            if (filled($this->saveCallback) && filled($chunkTranslations)) {
                ($this->saveCallback)($chunkTranslations);
            }

            $processedChunks++;
            $this->callProgressCallback($processedChunks, $totalChunks, extraData: [
                'language' => $this->toLanguage,
            ]);
        }

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

        // Use Process Pool for multiple languages by default
        if (count($toLanguages) > 1) {
            return self::quickTranslateWithPool($strings, $toLanguages, $fromLanguage, $singleToLanguage);
        }

        // Single language processing
        foreach ($toLanguages as $language) {
            if (! is_string($language)) {
                continue;
            }

            if (blank($language)) {
                continue;
            }
            $result = self::configure()
                ->from($fromLanguage)
                ->to($language)
                ->run($strings);

            $translatedStrings->put($language, $result);
        }

        // If only one target language is specified, return the first translated string
        if ($singleToLanguage && $translatedStrings->isNotEmpty()) {
            return $translatedStrings->first();
        }

        return $translatedStrings->toArray();
    }

    protected static function quickTranslateWithPool(array $strings, array $toLanguages, string $fromLanguage, bool $singleToLanguage): array
    {
        $tempDir = storage_path('app/langfy/temp');
        File::ensureDirectoryExists($tempDir);

        $batchId           = Str::uuid();
        $inputFiles        = [];
        $outputFiles       = [];
        $translatedStrings = collect();

        // Prepare input files for each language
        foreach ($toLanguages as $index => $language) {
            if (! is_string($language)) {
                continue;
            }

            if (blank($language)) {
                continue;
            }
            $inputFile  = "{$tempDir}/quicktrans_input_{$batchId}_{$index}.json";
            $outputFile = "{$tempDir}/quicktrans_output_{$batchId}_{$index}.json";

            file_put_contents($inputFile, json_encode($strings));

            $inputFiles[$language]  = $inputFile;
            $outputFiles[$language] = $outputFile;
        }

        try {
            // Create a process pool for multiple languages
            $pool = Process::pool(function (Pool $pool) use ($inputFiles, $outputFiles, $fromLanguage): void {
                foreach ($inputFiles as $language => $inputFile) {
                    $outputFile = $outputFiles[$language];

                    $command = sprintf(
                        'php %s langfy:translate-chunk "%s" "%s" --from="%s" --to="%s" --model="%s" --temperature="%s" --provider="%s"',
                        base_path('artisan'),
                        $inputFile,
                        $outputFile,
                        $fromLanguage,
                        $language,
                        config('langfy.ai.model'),
                        config('langfy.ai.temperature'),
                        config('langfy.ai.provider')
                    );

                    $pool->command($command);
                }
            });

            // Execute and wait for completion
            $results = $pool->start()->wait();

            // Collect translated results
            foreach ($outputFiles as $language => $outputFile) {
                if (file_exists($outputFile)) {
                    $languageTranslations = json_decode(file_get_contents($outputFile), true);

                    if ($languageTranslations && is_array($languageTranslations)) {
                        $translatedStrings->put($language, $languageTranslations);
                    }
                }
            }
        } finally {
            // Cleanup temp files
            foreach (array_merge($inputFiles, $outputFiles) as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }

        // If only one target language is specified, return the first translated string
        if ($singleToLanguage && $translatedStrings->isNotEmpty()) {
            return $translatedStrings->first();
        }

        return $translatedStrings->toArray();
    }

    public function translateChunk(Collection $chunk): array
    {
        return $this->getAIProvider()->translate($chunk->toArray());
    }

    protected function translateChunkInternal(Collection $chunk): array
    {
        return $this->translateChunk($chunk);
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

            // For retries, use sequential processing to avoid complexity
            $chunks              = collect($stringsToTranslate)->chunk($this->chunkSize);
            $missingTranslations = [];

            foreach ($chunks as $chunk) {
                $chunkTranslations = $this->translateChunkInternal($chunk);

                // Save chunk immediately if callback is provided
                if (filled($this->saveCallback) && filled($chunkTranslations)) {
                    ($this->saveCallback)($chunkTranslations);
                }

                $missingTranslations = array_merge($missingTranslations, $chunkTranslations);
            }

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
