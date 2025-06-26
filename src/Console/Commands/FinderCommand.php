<?php

declare(strict_types = 1);

namespace Langfy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Langfy\Langfy;
use Langfy\Services\Finder;
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
        $modulesEnabled   = Langfy::laravelModulesEnabled();

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

        if ($this->option('trans')) {
            $this->translateFoundStrings();

            return;
        }

        if ($this->shouldPromptForTranslation()) {
            $this->translateFoundStrings();
        }
    }

    private function shouldPromptForTranslation(): bool
    {
        return ! $this->option('no-trans') &&
            $this->confirm('Do you want translate founded strings?', false);
    }

    protected function processApplication(): void
    {
        $this->info('Starting in the main application');

        $paths = [
            app_path(),
            resource_path(),
            database_path(),
            base_path('routes'),
        ];

        $finder = Finder::in($paths);

        $strings     = $finder->run();
        $stringCount = count($strings);

        $this->info("Found {$stringCount} translatable strings");
        $this->totalStringsFound += $stringCount;
        $this->moduleStats['app'] = $stringCount;

        if ($stringCount > 0) {
            $this->saveStringsToFile($strings, 'app');
            $this->appTranslations = $strings;
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
        if (! Langfy::laravelModulesEnabled()) {
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
                    collect(Langfy::availableModules())
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

        $modulePath = Langfy::modulePath($moduleName);

        if (blank($modulePath) || ! is_dir($modulePath)) {
            $this->error("Module '{$moduleName}' not found.");

            return;
        }

        $finder = Finder::in($modulePath)
            ->ignore(['vendor', 'node_modules', 'storage', 'lang']);

        $strings     = $finder->run();
        $stringCount = count($strings);

        $this->info("Found {$stringCount} translatable strings in {$moduleName}");
        $this->totalStringsFound += $stringCount;
        $this->moduleStats[$moduleName] = $stringCount;

        if ($stringCount > 0) {
            $this->saveStringsToFile($strings, $moduleName, $modulePath);
            $this->modulesToTranslate[$moduleName] = $strings;
        }
    }

    protected function saveStringsToFile(array $strings, string $context, ?string $basePath = null): void
    {
        $fromLanguage = config('langfy.from_language', 'en');

        $langPath = filled($basePath) ? $basePath . '/lang' : lang_path();
        $filePath = $langPath . '/' . $fromLanguage . '.json';

        Langfy::saveStringsToFile($strings, $filePath);

        $this->info("Saved {$context} strings to {$filePath}");
    }

    protected function translateFoundStrings(): void
    {
        $toLanguages = config('langfy.to_language', []);

        if (blank($toLanguages)) {
            $this->warn('No target languages configured in langfy.to_language');

            return;
        }

        $this->info('Target languages: ' . implode(', ', $toLanguages));

        // TODO: Implement translation logic
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
