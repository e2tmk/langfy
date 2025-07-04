<?php

declare(strict_types = 1);

namespace Langfy\Console\Commands;

use Illuminate\Console\Command;
use Langfy\Concerns\HandlesTargetProcessing;
use Langfy\Enums\Context;
use Langfy\Langfy;

class TransCommand extends Command
{
    use HandlesTargetProcessing;

    protected $signature = 'langfy:trans
                           {--to=* : Target languages to translate to (comma-separated)}
                           {--app : Run on the main application instead of modules}
                           {--modules=* : Specific modules to process (comma-separated)}
                           {--queue : Run translations asynchronously using queues}';

    protected $description = 'Translate strings in application or modules using AI';

    protected int $totalTranslations = 0;

    public function handle(): int
    {
        $toLanguages = $this->getTargetLanguages();

        if (blank($toLanguages)) {
            $this->error('No target languages specified. Please use --to option or configure langfy.to_language');

            return self::FAILURE;
        }

        $this->info('Target languages: ' . collect($toLanguages)->implode(', '));

        $this->processTargets();
        $this->displaySummary();

        return self::SUCCESS;
    }

    protected function getTargetLanguages(): array
    {
        $toOption = $this->option('to');

        // If --to option is provided, use it
        if (filled($toOption)) {
            // Handle both array format and comma-separated string
            if (is_array($toOption)) {
                $languages = [];

                foreach ($toOption as $lang) {
                    if (str_contains((string) $lang, ',')) {
                        $languages = array_merge($languages, explode(',', (string) $lang));
                    } else {
                        $languages[] = $lang;
                    }
                }

                return array_map('trim', $languages);
            }

            return array_map('trim', explode(',', $toOption));
        }

        // Fall back to configuration
        return config()->array('langfy.to_language', []);
    }

    protected function performLangfyOperation(Context $context, ?string $moduleName = null): array
    {
        $toLanguages = $this->getTargetLanguages();
        $isAsync     = $this->option('queue');
        $area        = $moduleName ?? 'application';

        $isAsync
            ? $this->info("Dispatching async translation jobs for {$area}...")
            : $this->info("Translating {$area} strings...");

        $langfy = filled($moduleName)
            ? Langfy::for($context, $moduleName)
            : Langfy::for($context);

        $langfy = $langfy->translate(to: $toLanguages);

        // Enable async mode if any async flag is present
        $isAsync
            ? $langfy->async()
            : $langfy->onTranslateProgress(function (int $current, int $total, array $extraData = []) use ($area): void {
                $language   = $extraData['language'] ?? 'unknown';
                $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
                $this->info("Translating {$area} to {$language}: {$current}/{$total} ({$percentage}%)");
            });

        $result = $langfy->perform();

        if (! isset($result['translations'])) {
            return [
                'count'           => 0,
                'translations'    => [],
                'context'         => $context,
                'module'          => $moduleName,
                'async'           => $isAsync,
                'jobs_dispatched' => 0,
            ];
        }

        $jobsDispatched = 0;
        $totalStrings   = 0;

        foreach ($result['translations'] as $languageData) {
            if ($isAsync && ($languageData['job_dispatched'] ?? false)) {
                $jobsDispatched++;
                $totalStrings += $languageData['strings_count'] ?? 0;
            } elseif (! $isAsync) {
                $totalStrings += count($languageData);
            }
        }

        $isAsync
            ? $this->info("Dispatched {$jobsDispatched} translation jobs for {$totalStrings} strings in {$area}")
            : $this->info("Translated {$totalStrings} strings in {$area}");

        return [
            'count'           => $totalStrings,
            'translations'    => $result['translations'],
            'context'         => $context,
            'module'          => $moduleName,
            'async'           => $isAsync,
            'jobs_dispatched' => $jobsDispatched,
        ];
    }

    protected function updateResults(string $key, array $result): void
    {
        $this->results[$key] = $result;
        $this->totalTranslations += $result['count'];
    }

    protected function displaySummary(): void
    {
        $this->newLine(2);
        $this->info('Translation completed...');
        $this->info("{$this->totalTranslations} translations created in total");

        $resultsWithTranslations = array_filter($this->results, fn ($result): bool => $result['count'] > 0);

        if (blank($resultsWithTranslations)) {
            $this->info('No translations were created.');

            return;
        }

        $tableData = [];

        foreach ($resultsWithTranslations as $key => $result) {
            $areaName    = $key === 'app' ? 'Application' : $key;
            $tableData[] = [$areaName, $result['count']];
        }

        $this->table(['Area', 'Translations Created'], $tableData);
    }
}
