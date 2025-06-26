<?php

declare(strict_types = 1);

namespace Langfy\Concerns;

use Illuminate\Support\Str;
use Langfy\Enums\Context;
use Langfy\Langfy;
use Nwidart\Modules\Facades\Module;

trait HandlesTargetProcessing
{
    protected array $results = [];

    protected function processTargets(): void
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

    protected function shouldProcessApp(): bool
    {
        return $this->confirm('Do you want to process the main application?', true);
    }

    protected function shouldProcessModules(bool $hasAppOption): bool
    {
        $message = $hasAppOption
            ? 'Do you want to process modules as well?'
            : 'Do you want to process modules?';

        return $this->confirm($message, false);
    }

    protected function processApplication(): void
    {
        $result = $this->performLangfyOperation(Context::Application);
        $this->updateResults('app', $result);
    }

    protected function processSpecificModules(array $modules): void
    {
        foreach ($modules as $module) {
            $this->processModule($module);
        }

        // Ask to process the application after processing specific modules
        if ($this->confirm('Do you want to process the application as well?', false)) {
            $this->processApplication();
        }
    }

    protected function processModulesWithSelection(): void
    {
        $modules = $this->getAvailableModules();

        if (blank($modules)) {
            return;
        }

        $selectedModules = $this->selectModules($modules);
        $this->processSelectedModules($selectedModules);
    }

    protected function getAvailableModules(): array
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

    protected function selectModules(array $modules): array
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

    protected function processSelectedModules(array $selectedModules): void
    {
        foreach ($selectedModules as $moduleName) {
            $moduleName = Str::title(trim((string) $moduleName));

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

    protected function processModule(string $moduleName): void
    {
        $this->info("Starting in \"{$moduleName} Module\"");

        $result = $this->performLangfyOperation(Context::Module, $moduleName);
        $this->updateResults($moduleName, $result);
    }

    protected function updateResults(string $key, array $result): void
    {
        $this->results[$key] = $result;
    }

    /**
     * Perform the specific Langfy operation for the given context.
     * This method should be implemented by the command using this trait.
     */
    abstract protected function performLangfyOperation(Context $context, ?string $moduleName = null): array;
}
