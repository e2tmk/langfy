<?php

declare(strict_types = 1);

namespace Langfy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Langfy\Enums\Context;
use Langfy\Langfy;
use Nwidart\Modules\Facades\Module;

class FinderCommand extends Command
{
    protected $signature = 'langfy:finder
                           {--app : Run on the main application instead of modules}
                           {--modules=* : Specific modules to process (comma-separated)}
                           {--no-trans : Skip translation prompt}
                           {--trans : Auto-translate without prompting}';

    protected $description = 'Find translation strings in application or modules and update language files';

    protected array $results = [];

    protected int $totalStringsFound = 0;

    public function handle(): int
    {
        $this->processTargets();
        $this->displaySummary();
        $this->handleTranslation();

        return self::SUCCESS;
    }

    private function processTargets(): void
    {
        $hasAppOption     = $this->option('app');
        $hasModulesOption = filled($this->option('modules'));
        $modulesEnabled   = Langfy::utils()->laravelModulesEnabled();

        // Process the app only if the option is set or if modules are not enabled
        if ($hasAppOption || (! $hasModulesOption && $this->shouldProcessApp())) {
            $this->processApplication();
        }

        // Process modules if the option is set or if modules are enabled
        if ($hasModulesOption) {
            $this->processSpecificModules($this->option('modules'));
        } elseif ($modulesEnabled && $this->shouldProcessModules($hasAppOption)) {
            $this->processModulesWithSelection();
        }

        if (blank($this->results)) {
            $this->info('No processing selected. Exiting.');
        }
    }

    private function shouldProcessApp(): bool
    {
        return $this->confirm('Do you want to process the main application?', true);
    }

    private function shouldProcessModules(bool $hasAppOption): bool
    {
        $message = $hasAppOption
            ? 'Do you want to process modules as well?'
            : 'Do you want to process modules?';

        return $this->confirm($message, false);
    }

    private function processApplication(): void
    {
        $result = $this->performLangfyOperation(Context::Application);
        $this->updateResults('app', $result);
    }

    private function processSpecificModules(array $modules): void
    {
        foreach ($modules as $module) {
            $this->processModule($module);
        }

        // Ask to process the application after processing specific modules
        if ($this->confirm('Do you want to process the application as well?', false)) {
            $this->processApplication();
        }
    }

    private function processModulesWithSelection(): void
    {
        $modules = $this->getAvailableModules();

        if (empty($modules)) {
            return;
        }

        $selectedModules = $this->selectModules($modules);
        $this->processSelectedModules($selectedModules);
    }

    private function getAvailableModules(): array
    {
        if (! Langfy::utils()->laravelModulesEnabled()) {
            $this->error('Modules are not enabled in this application.');

            return [];
        }

        $modules = Module::allEnabled();

        if (blank($modules)) {
            $this->info('No enabled modules found.');

            return [];
        }

        return $modules;
    }

    private function selectModules(array $modules): array
    {
        $choices = ['None', 'All'];

        foreach ($modules as $module) {
            $choices[] = $module->getName();
        }

        return $this->choice(
            'Which modules do you want to process? (Use comma to separate multiple)',
            $choices,
            0,
            null,
            true
        );
    }

    private function processSelectedModules(array $selectedModules): void
    {
        foreach ($selectedModules as $moduleName) {
            $moduleName = Str::title(trim($moduleName));

            if ($moduleName === 'None') {
                $this->info('No modules selected. Skipping module processing.');

                return;
            }

            if ($moduleName === 'All') {
                foreach (Langfy::utils()->availableModules() as $name) {
                    $this->processModule($name);
                }

                return;
            }

            $this->processModule($moduleName);
        }
    }

    private function processModule(string $moduleName): void
    {
        $this->info("Starting in \"{$moduleName} Module\"");

        $result = $this->performLangfyOperation(Context::Module, $moduleName);
        $this->updateResults($moduleName, $result);
    }

    private function performLangfyOperation(Context $context, ?string $moduleName = null): array
    {
        $langfy = $moduleName
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

    private function updateResults(string $key, array $result): void
    {
        $this->results[$key] = $result;
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

        $this->performTranslations();
    }

    private function performTranslations(): void
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

            $this->translateResult($key, $result, $toLanguages->toArray());
        }
    }

    private function translateResult(string $key, array $result, array $toLanguages): void
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

        $resultsWithStrings = array_filter($this->results, fn ($result) => $result['count'] > 0);

        if (empty($resultsWithStrings)) {
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
