<?php

declare(strict_types = 1);

namespace Langfy\Livewire;

use Langfy\Enums\Context;
use Langfy\Langfy;
use Livewire\Component;

class LangfyView extends Component
{
    public string $activeLanguage = '';

    public bool $isInitializing = false;

    public bool $isTranslating = false;

    public array $availableLanguages = [];

    public array $translations = [];

    public function mount(): void
    {
        if (blank($this->availableLanguages)) {
            $this->loadAvailableLanguages();
        }

        if (blank($this->activeLanguage)) {
            $this->activeLanguage = $this->availableLanguages[0];
        }

        $this->loadStrings();
    }

    public function loadAvailableLanguages(): void
    {
        $this->availableLanguages = Langfy::for(Context::Application)
            ->getLanguages();
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

        $result = Langfy::for(Context::Application)
            ->getAllStringsFor($this->activeLanguage);

        $this->translations = collect($result)
            ->toArray();
    }

    public function render()
    {
        return view('langfy::livewire.langfy-view')
            ->layout('langfy::components.layouts.app');
    }
}
