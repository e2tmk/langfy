<?php

declare(strict_types = 1);

namespace Langfy\Console\Commands;

use Illuminate\Console\Command;
use Langfy\Concerns\HandlesTargetProcessing;
use Langfy\Enums\Context;
use Langfy\Langfy;

class FinderCommand extends Command
{
    use HandlesTargetProcessing;

    protected $signature = 'langfy:finder
                           {--app : Run on the main application instead of modules}
                           {--modules=* : Specific modules to process (comma-separated)}
                           {--no-trans : Skip translation prompt}
                           {--trans : Auto-translate without prompting}';

    protected $description = 'Find translation strings in application or modules and update language files';

    protected int $totalStringsFound = 0;

    public function handle(): int
    {
        $this->processTargets();
        $this->displaySummary();
        $this->handleTranslation();

        return self::SUCCESS;
    }

    protected function performLangfyOperation(Context $context, ?string $moduleName = null): array
    {
        $langfy = $moduleName !== null && $moduleName !== '' && $moduleName !== '0'
            ? Langfy::for($context, $moduleName)
            : Langfy::for($context);

        $langfy = $langfy->finder()->save();

        $result      = $langfy->perform();
        $stringCount = $result['found_strings'] ?? 0;

        $area = $moduleName ?? 'main application';
        $this->info("Found {$stringCount} translatable strings in {$area}");

        return [
            'count'   => $stringCount,
            'strings' => $stringCount > 0 ? $langfy->getStrings() : [],
            'context' => $context,
            'module'  => $moduleName,
        ];
    }

    protected function updateResults(string $key, array $result): void
    {
        parent::updateResults($key, $result);
        $this->totalStringsFound += $result['count'];
    }

    private function handleTranslation(): void
    {
        if ($this->totalStringsFound === 0) {
            return;
        }

        $shouldTranslate = $this->option('trans') ||
            (! $this->option('no-trans') &&
                $this->confirm('Do you want translate founded strings?', false));

        if (! $shouldTranslate) {
            return;
        }

        // Get areas to translate with selection
        $areasToTranslate = $this->selectAreasForTranslation();

        if (blank($areasToTranslate)) {
            $this->info('No areas selected for translation.');

            return;
        }

        $this->performTranslations($areasToTranslate);
    }

    private function selectAreasForTranslation(): array
    {
        $resultsWithStrings = array_filter($this->results, fn ($result): bool => $result['count'] > 0);

        if (blank($resultsWithStrings)) {
            return [];
        }

        // If only one area has strings, auto-select it
        if (count($resultsWithStrings) === 1) {
            return array_keys($resultsWithStrings);
        }

        $choices = ['None', 'All'];

        foreach ($resultsWithStrings as $key => $result) {
            $areaName    = $key === 'app' ? 'Application' : $key;
            $stringCount = $result['count'];
            $choices[]   = "{$areaName} ({$stringCount} strings)";
        }

        $selectedChoices = $this->choice(
            'Which areas do you want to translate? (Use comma to separate multiple)',
            $choices,
            0,
            null,
            true
        );

        return $this->processTranslationSelection($selectedChoices, $resultsWithStrings);
    }

    private function processTranslationSelection(array $selectedChoices, array $resultsWithStrings): array
    {
        $areasToTranslate = [];

        foreach ($selectedChoices as $choice) {
            $choice = trim((string) $choice);

            if ($choice === 'None') {
                return [];
            }

            if ($choice === 'All') {
                return array_keys($resultsWithStrings);
            }

            // Extract area name from choice (remove string count part)
            if (preg_match('/^(.+?)\s*\(\d+\s+strings\)$/', $choice, $matches)) {
                $areaName = trim($matches[1]);

                if ($areaName === 'Application') {
                    $areasToTranslate[] = 'app';
                } else {
                    // Find the corresponding key in results
                    foreach (array_keys($resultsWithStrings) as $key) {
                        if ($key === $areaName || ($key === 'app' && $areaName === 'Application')) {
                            $areasToTranslate[] = $key;

                            break;
                        }
                    }
                }
            }
        }

        return array_unique($areasToTranslate);
    }

    private function performTranslations(array $areasToTranslate = []): void
    {
        $toLanguages = collect(config()->array('langfy.to_language', []));

        if ($toLanguages->isEmpty()) {
            $this->warn('No target languages configured in langfy.to_language');

            return;
        }

        $this->info('Translating found strings...');
        $this->info('Target languages: ' . $toLanguages->implode(', '));

        foreach ($this->results as $key => $result) {
            if ($result['count'] === 0) {
                continue;
            }

            // Skip if this area is not selected for translation
            if ($areasToTranslate !== [] && ! in_array($key, $areasToTranslate)) {
                continue;
            }

            $this->translateResult($result, $toLanguages->toArray());
        }
    }

    private function translateResult(array $result, array $toLanguages): void
    {
        $area = $result['module'] ?? 'application';
        $this->info("Translating {$area} strings...");

        $langfy = $result['module']
            ? Langfy::for($result['context'], $result['module'])
            : Langfy::for($result['context']);

        $langfy = $langfy->translate(to: $toLanguages)
            ->onTranslateProgress(function (int $current, int $total, string $language) use ($area): void {
                $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
                $this->info("Translating {$area} to {$language}: {$current}/{$total} ({$percentage}%)");
            });

        $langfy->perform();
    }

    private function displaySummary(): void
    {
        $this->newLine(2);
        $this->info('Finish...');
        $this->info("{$this->totalStringsFound} strings found in total");

        $resultsWithStrings = array_filter($this->results, fn ($result): bool => $result['count'] > 0);

        if (blank($resultsWithStrings)) {
            $this->info('No translatable strings were found.');

            return;
        }

        $tableData = [];

        foreach ($resultsWithStrings as $key => $result) {
            $areaName    = $key === 'app' ? 'Application' : $key;
            $tableData[] = [$areaName, $result['count']];
        }

        $this->table(['Area', 'Strings Found'], $tableData);
    }
}
