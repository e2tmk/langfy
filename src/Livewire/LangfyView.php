<?php

declare(strict_types = 1);

namespace Langfy\Livewire;

use Langfy\Enums\Context;
use Langfy\Langfy;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

class LangfyView extends Component
{
    use Interactions;

    public string $activeLanguage = '';

    public string $activeContext = 'application';

    public string $activeModule = '';

    public bool $isInitializing = false;

    public bool $isTranslating = false;

    public int $translationProgress = 0;

    public array $availableLanguages = [];

    public array $availableModules = [];

    public array $translations = [];

    public string $editingKey = '';

    public string $editingValue = '';

    protected function rules()
    {
        return [
            'editingValue' => 'required|max:2000',
        ];
    }

    public function mount(): void
    {
        $this->resetErrorBag();
        $this->loadAvailableModules();
        $this->loadAvailableLanguages();

        if (blank($this->activeLanguage) && filled($this->availableLanguages)) {
            $this->activeLanguage = $this->availableLanguages[0];
        }

        $this->loadStrings();
    }

    public function loadAvailableModules(): void
    {
        $this->availableModules = Langfy::utils()->availableModules();
    }

    public function loadAvailableLanguages(): void
    {
        $context    = $this->activeContext === 'application' ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

        $this->availableLanguages = Langfy::for($context, $moduleName)
            ->getLanguages();
    }

    public function updatedActiveContext(): void
    {
        if ($this->activeContext === 'module' && blank($this->activeModule) && filled($this->availableModules)) {
            $this->activeModule = array_values($this->availableModules)[0];
        }

        if ($this->activeContext === 'application') {
            $this->activeModule = '';
        }

        $this->loadAvailableLanguages();
        $this->loadStrings();
    }

    public function updatedActiveModule(): void
    {
        $this->loadAvailableLanguages();
        $this->loadStrings();
    }

    public function updatedActiveLanguage(): void
    {
        $this->loadStrings();
    }

    public function reloadStrings(): void
    {
        if (blank($this->activeLanguage)) {
            return;
        }

        $this->loadStrings();
    }

    public function loadStrings(): void
    {
        if (blank($this->activeLanguage)) {
            $this->translations = [];

            return;
        }

        $context    = $this->activeContext === 'application' ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

        $strings = Langfy::for($context, $moduleName)
            ->getStrings();

        Langfy::utils()->synchronizeStringsFiles($strings, $this->getTranslationFilePath());

        $allStrings = Langfy::for($context, $moduleName)
            ->getAllStringsFor($this->activeLanguage);

        $this->translations = collect($allStrings)
            ->map(function ($value, $key) use ($moduleName) {
                return [
                    'key'    => $key,
                    'value'  => $value,
                    'module' => $moduleName,
                ];
            })
            ->values()
            ->toArray();
    }

    public function refreshStrings(): void
    {
        $this->isInitializing = true;

        try {
            $context    = $this->activeContext === 'application' ? Context::Application : Context::Module;
            $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

            Langfy::for($context, $moduleName)
                ->finder()
                ->save()
                ->perform();

            $this->loadAvailableLanguages();
            $this->loadStrings();
        } catch (\Exception $e) {
            $this->notify('error', "Error refreshing strings: {$e->getMessage()}");
        } finally {
            $this->isInitializing = false;
        }
    }

    public function translateMissingStrings(bool $allLanguages = false): void
    {
        $this->isTranslating       = true;
        $this->translationProgress = 1;

        try {
            $context    = $this->activeContext === 'application' ? Context::Application : Context::Module;
            $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

            $result = Langfy::for($context, $moduleName)
                ->finder()
                ->save()
                ->translate(to: $allLanguages ? $this->availableLanguages : $this->activeLanguage)
                ->perform();

            $translationCount = collect($result['translations'] ?? [])->sum(fn ($lang) => count($lang));

            $this->notify('success', "Successfully translated {$translationCount} strings!");
        } catch (\Exception $e) {
            $this->notify('error', 'Error translating strings: ' . $e->getMessage());
        } finally {
            $this->isTranslating       = false;
            $this->translationProgress = 0;
        }
    }

    public function render()
    {
        return view('langfy::livewire.langfy-view')
            ->layout('langfy::components.layouts.app');
    }

    public function startEdit($key, $value): void
    {
        $this->editingKey   = $key;
        $this->editingValue = $value ?? '';
        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        $this->validate();

        try {
            $this->updateTranslationFile($this->editingKey, $this->editingValue);

            $this->translations = collect($this->translations)
                ->map(function ($item) {
                    if ($item['key'] === $this->editingKey) {
                        $item['value'] = $this->editingValue;
                    }

                    return $item;
                })
                ->toArray();

            $this->cancelEdit();

            $this->notify('success', 'Translation updated successfully!');
        } catch (\Exception $e) {
            $this->addError('editingValue', 'Failed to update translation: ' . $e->getMessage());
        }
    }

    public function cancelEdit(): void
    {
        $this->editingKey   = '';
        $this->editingValue = '';
        $this->resetErrorBag();
    }

    private function updateTranslationFile($key, $value): void
    {
        $filePath = $this->getTranslationFilePath();

        Langfy::utils()->saveStringsToFile([$key => $value], $filePath);
    }

    private function getTranslationFilePath(): string
    {
        $context    = $this->activeContext === 'application' ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

        return Langfy::for($context, $moduleName)
            ->getLanguageFilePath($this->activeLanguage);
    }

    public function updatedEditingValue(): void
    {
        $this->validateOnly('editingValue');
    }

    public function notify(string $type, string $message): void
    {
        match ($type) {
            'error'   => $this->toast()->error($message)->send(),
            'warning' => $this->toast()->warning($message)->send(),
            'info'    => $this->toast()->info($message)->send(),
            default   => $this->toast()->success($message)->send(),
        };
    }

    private const CONTEXT_OPTIONS = [
        ['label' => 'Application', 'value' => Context::Application->value],
        ['label' => 'Module', 'value' => Context::Module->value],
    ];

    public function getContextOptionsProperty(): array
    {
        if (empty($this->availableModules)) {
            return collect(self::CONTEXT_OPTIONS)
                ->where('value', Context::Application->value)
                ->values()
                ->toArray();
        }

        return self::CONTEXT_OPTIONS;
    }

    public function getModuleOptionsProperty(): array
    {
        return collect($this->availableModules)
            ->map(fn ($module) => ['label' => $module, 'value' => $module])
            ->toArray();
    }

    public function shouldShowModuleSelection(): bool
    {
        return $this->activeContext === 'module' && filled($this->availableModules);
    }
}
