<?php

declare(strict_types = 1);

namespace Langfy\Livewire;

use Livewire\Component;

class LangfyView extends Component
{
    public array $metadata = [
        [
            'key' => 'php',
            'value' => 'Laravel',
        ],
        [
            'key' => 'vuejs',
            'value' => 'NuxtJS',
        ]
    ];

    public function render()
    {
        return view('langfy::livewire.langfy-view')
            ->layout('langfy::components.layouts.app');
    }
}
