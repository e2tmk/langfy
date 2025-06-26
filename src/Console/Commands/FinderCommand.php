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

    protected array $moduleStats = [];

    protected int $totalStringsFound = 0;

    protected array $modulesToTranslate = [];

    protected array $appTranslations = [];

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

        // Case 1: --app specified
        if ($hasAppOption) {
            $this->processApplication();

            if ($modulesEnabled && $this->confirm('Do you want to process modules as well?', false)) {
                $this->processModulesWithSelection();
            }

            return;
        }

        // Case 2: --modules specified
        if ($hasModulesOption) {
            $this->processSpecificModules($this->option('modules'));

            if ($this->confirm('Do you want to process the application as well?', false)) {
                $this->processApplication();
            }

            return;
        }

        // Case 3: No options specified, prompt user
        $processApp     = $this->confirm('Do you want to process the main application?', true);
        $processModules = false;

        if ($modulesEnabled) {
            $processModules = $this->confirm('Do you want to process modules?', false);
        }

        if ($processApp) {
            $this->processApplication();
        }

        if ($processModules) {
            $this->processModulesWithSelection();
        }

        if (! $processApp && ! $processModules) {
            $this->info('No processing selected. Exiting.');
        }
    }

    private function processModules(): void
    {
        $modules = $this->option('modules');

        if (filled($modules)) {
            $this->processSpecificModules($modules);

            return;
        }

        $this->processModulesWithSelection();
    }

    private function handleTranslation(): void
    {
        if ($this->totalStringsFound === 0) {
            return;
        }

        $shouldTranslate = $this->option('trans') || $this->shouldPromptForTranslation();

        if (! $shouldTranslate) {
            return;
        }

        $this->info('Translating found strings...');
        $this->translateFoundStrings();
    }

    private function shouldPromptForTranslation(): bool
    {
        return ! $this->option('no-trans') &&
            $this->confirm('Do you want translate founded strings?', false);
    }

    protected function processApplication(): void
    {
        $this->info('Starting in the main application');

        $langfy = Langfy::for(Context::Application)
            ->finder()
            ->save();

        $result      = $langfy->perform();
        $stringCount = $result['found_strings'] ?? 0;

        $this->info("Found {$stringCount} translatable strings");
        $this->totalStringsFound += $stringCount;
        $this->moduleStats['app'] = $stringCount;

        if ($stringCount > 0) {
            $this->appTranslations = $langfy->getStrings();
        }
    }

    protected function processSpecificModules(array $modules): void
    {
        foreach ($modules as $module) {
            $this->processModule($module);
        }
    }

    protected function processModulesWithSelection(): void
    {
        if (! Langfy::utils()->laravelModulesEnabled()) {
            $this->error('Modules are not enabled in this application.');

            return;
        }

        $modules = Module::allEnabled();

        if (blank($modules)) {
            $this->info('No enabled modules found.');

            return;
        }

        $moduleChoices = [
            'None',
            'All',
        ];

        foreach ($modules as $module) {
            $moduleChoices[] = $module->getName();
        }

        // 0 Represents the default choice, which is 'None', and 1 represents 'All'
        $selectedModules = $this->choice(
            'Which modules do you want to process? (Use comma to separate multiple)',
            $moduleChoices,
            0, // Default to the first option
            null,
            true
        );

        $skipAll = false;

        collect($selectedModules)
            ->each(function (string $moduleName) use (&$skipAll): void {
                if ($skipAll) {
                    return;
                }

                $moduleName = Str::of($moduleName)->trim()->lower()->title()->toString();

                if ($moduleName === 'None') {
                    $this->info('No modules selected. Skipping module processing.');
                    $skipAll = true;

                    return;
                }

                if ($moduleName === 'All') {
                    collect(Langfy::utils()->availableModules())
                        ->each(fn ($name) => $this->processModule($name));

                    $skipAll = true;

                    return;
                }

                $this->processModule($moduleName);
            });
    }

    protected function processModule(string $moduleName): void
    {
        $this->info("Starting in \"{$moduleName} Module\"");

        $langfy = Langfy::for(Context::Module, $moduleName)
            ->finder()
            ->save()
            ->onFinderProgress(function (int $current, int $total, array $extraData = []) use ($moduleName): void {
                $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
                $this->info("Processing {$moduleName}: {$current}/{$total} ({$percentage}%) - {$extraData['file']}");
            });

        $result      = $langfy->perform();
        $stringCount = $result['found_strings'] ?? 0;

        $this->info("Found {$stringCount} translatable strings in {$moduleName}");
        $this->totalStringsFound += $stringCount;
        $this->moduleStats[$moduleName] = $stringCount;

        if ($stringCount > 0) {
            $this->modulesToTranslate[$moduleName] = $langfy->getStrings();
        }
    }

    protected function translateFoundStrings(): void
    {
        $toLanguages = collect(config()->array('langfy.to_language', []));

        if (blank($toLanguages)) {
            $this->warn('No target languages configured in langfy.to_language');

            return;
        }

        $this->info('Target languages: ' . implode(', ', $toLanguages->toArray()));

        // Translate application strings
        if (filled($this->appTranslations)) {
            $this->info('Translating application strings...');

            $langfy = Langfy::for(Context::Application)
                ->translate(to: $toLanguages->toArray())
                ->onTranslateProgress(function (int $current, int $total, string $language): void {
                    $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
                    $this->info("Translating to {$language}: {$current}/{$total} ({$percentage}%)");
                });

            $langfy->perform();
        }

        // Translate module strings
        foreach ($this->modulesToTranslate as $moduleName => $strings) {
            if (blank($strings)) {
                continue;
            }

            $this->info("Translating {$moduleName} module strings...");

            $langfy = Langfy::for(Context::Module, $moduleName)
                ->translate(to: $toLanguages->toArray())
                ->onTranslateProgress(function (int $current, int $total, string $language) use ($moduleName): void {
                    $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
                    $this->info("Translating {$moduleName} to {$language}: {$current}/{$total} ({$percentage}%)");
                });

            $langfy->perform();
        }
    }

    protected function displaySummary(): void
    {
        $this->newLine(2);
        $this->info('Finish...');
        $this->info("{$this->totalStringsFound} strings found in total");

        $updatedAreas = array_filter($this->moduleStats, fn ($count): bool => $count > 0);

        if ($updatedAreas === []) {
            $this->info('No translatable strings were found.');

            return;
        }

        $tableData = [];

        foreach ($updatedAreas as $area => $stringCount) {
            $tableData[] = [$area === 'app' ? 'Application' : $area, $stringCount];
        }

        $this->table(['Area', 'Strings Found'], $tableData);
    }
}
