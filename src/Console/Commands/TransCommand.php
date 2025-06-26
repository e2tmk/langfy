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
                           {--modules=* : Specific modules to process (comma-separated)}';

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

        $langfy = $moduleName !== null && $moduleName !== '' && $moduleName !== '0'
            ? Langfy::for($context, $moduleName)
            : Langfy::for($context);

        $langfy = $langfy->translate(to: $toLanguages)
            ->onTranslateProgress(function (int $current, int $total, array $extraData = []) use ($moduleName): void {
                $area       = $moduleName ?? 'application';
                $language   = $extraData['language'] ?? 'unknown';
                $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
                $this->info("Translating {$area} to {$language}: {$current}/{$total} ({$percentage}%)");
            });

        $result = $langfy->perform();

        $translationCount = 0;

        if (isset($result['translations']) && is_array($result['translations'])) {
            foreach ($result['translations'] as $translations) {
                $translationCount += count($translations);
            }
        }

        $area = $moduleName ?? 'main application';
        $this->info("Translated {$translationCount} strings in {$area}");

        return [
            'count'        => $translationCount,
            'translations' => $result['translations'] ?? [],
            'context'      => $context,
            'module'       => $moduleName,
        ];
    }

    protected function updateResults(string $key, array $result): void
    {
        parent::updateResults($key, $result);
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
