<?php

declare(strict_types = 1);

namespace Langfy\Livewire;

use Langfy\Enums\Context;
use Langfy\Langfy;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

class LangfyPage extends Component
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
        [$context, $moduleName] = $this->getContextAndModule();

        $this->availableLanguages = Langfy::for($context, $moduleName)
            ->getLanguages();
    }

    public function updatedActiveContext(): void
    {
        if ($this->activeContext === Context::Module->value && blank($this->activeModule) && filled($this->availableModules)) {
            $this->activeModule = array_values($this->availableModules)[0];
        }

        if ($this->activeContext === Context::Application->value) {
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

        [$context, $moduleName] = $this->getContextAndModule();

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

    public function notify(string $type, string $message): void
    {
        match ($type) {
            'error'   => $this->toast()->error($message)->send(),
            'warning' => $this->toast()->warning($message)->send(),
            'info'    => $this->toast()->info($message)->send(),
            default   => $this->toast()->success($message)->send(),
        };
    }

    public function refreshStrings(): void
    {
        $this->isInitializing = true;

        try {
            [$context, $moduleName] = $this->getContextAndModule();

            Langfy::for($context, $moduleName)
                ->finder()
                ->save()
                ->perform();

            $this->loadAvailableLanguages();
            $this->loadStrings();
        } catch (\Exception $e) {
            $this->notify('error', __('Error refreshing strings: :message', ['message' => $e->getMessage()]));
        } finally {
            $this->isInitializing = false;
        }
    }

    public function translateMissingStrings(bool $allLanguages = false): void
    {
        $this->isTranslating       = true;
        $this->translationProgress = 1;

        try {
            [$context, $moduleName] = $this->getContextAndModule();

            $this->toast()
                ->persistent()
                ->info(__('Translation process is running asynchronously. Don\'t forget to run the queue worker!'))
                ->confirm(__('Confirm'))
                ->send();

            $result = Langfy::for($context, $moduleName)
                ->finder()
                ->save()
                ->async()
                ->translate(to: $allLanguages ? $this->availableLanguages : $this->activeLanguage)
                ->perform();
        } catch (\Exception $e) {
            $this->notify('error', __('Error translating strings: :message', ['message' => $e->getMessage()]));
        } finally {
            $this->isTranslating       = false;
            $this->translationProgress = 0;
        }
    }

    public function render()
    {
        return view('langfy::livewire.langfy-page')
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

            $this->notify('success', __('Translation updated successfully!'));
        } catch (\Exception $e) {
            $this->addError('editingValue', __('Failed to update translation: :message', ['message' => $e->getMessage()]));
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
        [$context, $moduleName] = $this->getContextAndModule();

        return Langfy::for($context, $moduleName)
            ->getLanguageFilePath($this->activeLanguage);
    }

    public function updatedEditingValue(): void
    {
        $this->validateOnly('editingValue');
    }

    private function getContextAndModule(): array
    {
        $context = $this->activeContext === Context::Application->value ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === Context::Module->value ? $this->activeModule : null;

        return [$context, $moduleName];
    }

    public function getContextOptionsProperty(): array
    {
        $baseOptions = [
            ['label' => __('Application'), 'value' => Context::Application->value],
            ['label' => __('Module'), 'value' => Context::Module->value],
        ];

        if (empty($this->availableModules)) {
            return collect($baseOptions)
                ->where('value', Context::Application->value)
                ->values()
                ->toArray();
        }

        return $baseOptions;
    }

    public function getModuleOptionsProperty(): array
    {
        return collect($this->availableModules)
            ->map(fn ($module) => ['label' => $module, 'value' => $module])
            ->toArray();
    }

    public function shouldShowModuleSelection(): bool
    {
        return $this->activeContext === Context::Module->value && filled($this->availableModules);
    }
}
