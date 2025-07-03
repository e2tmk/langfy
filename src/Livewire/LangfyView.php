<?php

declare(strict_types = 1);

namespace Langfy\Livewire;

use Langfy\Enums\Context;
use Langfy\Helpers\Utils;
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
        $this->availableModules = (new Utils)->availableModules();
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

        $context    = $this->activeContext === 'application' ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

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

            $result = Langfy::for($context, $moduleName)
                ->finder()
                ->save()
                ->perform();

            $this->loadAvailableLanguages();
            $this->loadStrings();

            $this->notify('success', "Encontrado {$result['found_strings']} strings");
        } catch (\Exception $e) {
            $this->notify('error', "Error refreshing strings: {$e->getMessage()}");
        } finally {
            $this->isInitializing = false;
        }
    }

    public function translateMissingStrings(bool $allLanguages = false): void
    {
        $this->isTranslating = true;

        try {
            $context    = $this->activeContext === 'application' ? Context::Application : Context::Module;
            $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

            $result = Langfy::for($context, $moduleName)
                ->finder()
                ->save()
                ->translate(to: $allLanguages ? $this->availableLanguages : $this->activeLanguage)
                ->async()
                ->perform();

            $this->loadStrings();

            $translationCount = collect($result['translations'] ?? [])->sum(fn ($lang) => count($lang));

            $this->notify('success', "Successfully translated {$translationCount} strings!");
        } catch (\Exception $e) {
            dump($e->getMessage(), [$e->getTrace()]);
            $this->notify('error', 'Error translating strings: ' . $e->getMessage());
        } finally {
            $this->isTranslating = false;
        }
    }

    protected function getLanguageFilePath(Langfy $langfy, string $language): string
    {
        $context    = $this->activeContext === 'application' ? Context::Application : Context::Module;
        $moduleName = $this->activeContext === 'module' ? $this->activeModule : null;

        if ($context === Context::Application) {
            return lang_path() . '/' . $language . '.json';
        }

        if ($context === Context::Module && filled($moduleName)) {
            $utils      = new Utils;
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
        $this->editingKey   = $key;
        $this->editingValue = $value ?? '';
        $this->resetErrorBag();
    }

    public function saveEdit()
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

    public function cancelEdit()
    {
        $this->editingKey   = '';
        $this->editingValue = '';
        $this->resetErrorBag();
    }

    private function updateTranslationFile($key, $value)
    {
        $filePath = $this->getTranslationFilePath();

        if (! file_exists($filePath)) {
            $this->createTranslationFile($filePath);
        }

        $translations = [];

        if (str_ends_with($filePath, '.json')) {
            $content      = file_get_contents($filePath);
            $translations = json_decode($content, true) ?? [];

            $translations[$key] = $value;

            file_put_contents($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $translations = include $filePath;

            if (strpos($key, '.') !== false) {
                $keys = explode('.', $key);
                $temp = &$translations;

                foreach ($keys as $k) {
                    if (! isset($temp[$k])) {
                        $temp[$k] = [];
                    }
                    $temp = &$temp[$k];
                }
                $temp = $value;
            } else {
                $translations[$key] = $value;
            }

            $content = "<?php\n\nreturn " . $this->arrayToString($translations) . ";\n";
            file_put_contents($filePath, $content);
        }
    }

    private function createTranslationFile($filePath)
    {
        $directory = dirname($filePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (str_ends_with($filePath, '.json')) {
            file_put_contents($filePath, '{}');
        } else {
            file_put_contents($filePath, "<?php\n\nreturn [];\n");
        }
    }

    private function arrayToString($array, $indent = 0)
    {
        $result  = "[\n";
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

        $result .= str_repeat('    ', $indent) . ']';

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

    public function notify(string $type, string $message): void
    {
        match ($type) {
            'success' => $this->toast()->success($message)->send(),
            'error' => $this->toast()->error($message)->send(),
            'warning' => $this->toast()->warning($message)->send(),
            'info' => $this->toast()->info($message)->send(),
            default => $this->toast()->success($message)->send(),
        };
    }
}
