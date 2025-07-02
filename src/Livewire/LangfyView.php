<?php

declare(strict_types = 1);

namespace Langfy\Livewire;

use Langfy\Enums\Context;
use Langfy\Helpers\Utils;
use Langfy\Langfy;
use Livewire\Component;

class LangfyView extends Component
{
    public string $activeLanguage = '';

    public string $activeContext = 'application';

    public string $activeModule = '';

    public bool $isInitializing = false;

    public bool $isTranslating = false;

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
        $utils = new Utils();
        $this->availableModules = $utils->availableModules();
    }

    public function loadAvailableLanguages(): void
    {
        $context = $this->activeContext === 'application' ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

        $this->availableLanguages = Langfy::for($context, $moduleName)
            ->getLanguages();
    }

    public function updatedActiveContext(): void
    {
        if ($this->activeContext === 'module' && blank($this->activeModule) && filled($this->availableModules)) {
            $this->activeModule = $this->availableModules[0];
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

        $context = $this->activeContext === 'application' ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

        $result = Langfy::for($context, $moduleName)
            ->getAllStringsFor($this->activeLanguage);

        // Transform the data to include module information
        $this->translations = collect($result)
            ->map(function ($value, $key) use ($moduleName) {
                return [
                    'key' => $key,
                    'value' => $value,
                    'module' => $moduleName,
                ];
            })
            ->toArray();
    }

    public function refreshStrings(): void
    {
        $this->isInitializing = true;

        try {
            $context = $this->activeContext === 'application' ? Context::Application : Context::Module;
            $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

            Langfy::for($context, $moduleName)
                ->finder()
                ->save()
                ->perform();

            $this->loadAvailableLanguages();
            $this->loadStrings();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Strings refreshed successfully!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error refreshing strings: ' . $e->getMessage()
            ]);
        } finally {
            $this->isInitializing = false;
        }
    }

    public function translateMissingStrings(): void
    {
        $this->isTranslating = true;

        try {
            $context = $this->activeContext === 'application' ? Context::Application : Context::Module;
            $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

            $result = Langfy::for($context, $moduleName)
                ->translate()
                ->perform();

            $this->loadStrings();

            $translationCount = collect($result['translations'] ?? [])->sum(fn($lang) => count($lang));

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully translated {$translationCount} strings!"
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error translating strings: ' . $e->getMessage()
            ]);
        } finally {
            $this->isTranslating = false;
        }
    }

    protected function getLanguageFilePath(Langfy $langfy, string $language): string
    {
        $context = $this->activeContext === 'application' ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

        if ($context === Context::Application) {
            return lang_path() . '/' . $language . '.json';
        }

        if ($context === Context::Module && filled($moduleName)) {
            $utils = new Utils();
            $modulePath = $utils->modulePath($moduleName);
            return $modulePath . '/lang/' . $language . '.json';
        }

        return lang_path() . '/' . $language . '.json';
    }

    public function render()
    {
        return view('langfy::livewire.langfy-view')
            ->layout('langfy::components.layouts.app');
    }

    public function startEdit($key, $value)
    {
        $this->editingKey = $key;
        $this->editingValue = $value;
        $this->resetErrorBag();
    }

    public function saveEdit()
    {
        $this->validate();

        try {
            $this->updateTranslationFile($this->editingKey, $this->editingValue);

            $this->translations = $this->translations->map(function ($item) {
                if ($item['key'] === $this->editingKey) {
                    $item['value'] = $this->editingValue;
                }
                return $item;
            });

            $this->cancelEdit();

            $this->dispatch('translation-updated', [
                'message' => 'Translation updated successfully!'
            ]);

        } catch (\Exception $e) {
            $this->addError('editingValue', 'Failed to update translation: ' . $e->getMessage());
        }
    }

    public function cancelEdit()
    {
        $this->editingKey = '';
        $this->editingValue = '';
        $this->resetErrorBag();
    }

    private function updateTranslationFile($key, $value)
    {
        $filePath = $this->getTranslationFilePath();

        if (!file_exists($filePath)) {
            throw new \Exception('Translation file not found');
        }

        $translations = include $filePath;

        // Atualizar o valor (suporta dot notation)
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $temp = &$translations;

            foreach ($keys as $k) {
                if (!isset($temp[$k])) {
                    $temp[$k] = [];
                }
                $temp = &$temp[$k];
            }
            $temp = $value;
        } else {
            $translations[$key] = $value;
        }

        // Salvar o arquivo com formatação adequada
        $content = "<?php\n\nreturn " . $this->arrayToString($translations) . ";\n";
        file_put_contents($filePath, $content);
    }

    private function arrayToString($array, $indent = 0)
    {
        $result = "[\n";
        $spacing = str_repeat('    ', $indent + 1);

        foreach ($array as $key => $value) {
            $result .= $spacing . "'" . addslashes($key) . "' => ";

            if (is_array($value)) {
                $result .= $this->arrayToString($value, $indent + 1);
            } else {
                $result .= "'" . addslashes($value) . "'";
            }

            $result .= ",\n";
        }

        $result .= str_repeat('    ', $indent) . "]";
        return $result;
    }

    private function getTranslationFilePath()
    {
        if ($this->activeContext === 'module' && $this->activeModule) {
            return base_path("Modules/{$this->activeModule}/lang/{$this->activeLanguage}.php");
        }

        return lang_path("{$this->activeLanguage}.php");
    }

    public function updatedEditingValue()
    {
        $this->validateOnly('editingValue');
    }
}
