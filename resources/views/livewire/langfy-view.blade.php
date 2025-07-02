<div class="min-h-screen bg-slate-50 dark:bg-slate-900">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
                        Translation Management
                    </h1>
                    <p class="text-slate-600 dark:text-slate-400">
                        Manage and translate your application strings with AI-powered assistance
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <x-button
                        color="slate"
                        wire:click="reloadStrings"
                        :loading="$isInitializing"
                        icon="arrow-path"
                        size="sm"
                    >
                        Refresh
                    </x-button>
                    <x-button
                        color="violet"
                        wire:click="translateMissingStrings"
                        :loading="$isTranslating"
                        icon="sparkles"
                    >
                        {{ $isTranslating ? 'Translating...' : 'Auto Translate Missing' }}
                    </x-button>
                </div>
            </div>
        </div>

        <!-- Context and Module Selection -->
        <div class="mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
                <div class="flex items-center space-x-4">
                    <!-- Context Selection -->
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Context
                        </label>
                        <select wire:model.live="activeContext" class="w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                            <option value="application">Application</option>
                            @if(filled($availableModules))
                                <option value="module">Module</option>
                            @endif
                        </select>
                    </div>

                    <!-- Module Selection (only shown when context is module) -->
                    @if($activeContext === 'module' && filled($availableModules))
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Module
                            </label>
                            <select wire:model.live="activeModule" class="w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                @foreach($availableModules as $module)
                                    <option value="{{ $module }}">{{ $module }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <!-- Current Selection Display -->
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Current Target
                        </label>
                        <div class="px-3 py-2 bg-slate-50 dark:bg-slate-700 rounded-md border border-slate-200 dark:border-slate-600">
                            <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                @if($activeContext === 'application')
                                    Application
                                @else
                                    Module: {{ $activeModule }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Language Tabs -->
        @if (filled($availableLanguages))
            <div class="mb-6">
                <x-tab wire:model.live="activeLanguage" x-on:navigate="$wire.reloadStrings()">
                    @foreach ($availableLanguages as $key => $language)
                        <x-tab.items tab="{{ $language }}" />
                    @endforeach
                    @if (filled($translations) && !$isInitializing)
                        <x-slot:header class="bg-violet-50 dark:bg-violet-950/20 border-b border-violet-200 dark:border-violet-800">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-violet-900 dark:text-violet-100">
                                    Translations for {{ strtoupper($activeLanguage) }}
                                </h3>
                            </div>
                        </x-slot:header>

                        <div class="overflow-x-auto">
                            <x-table :headers="[
                                    ['index' => 'key', 'label' => 'Key'],
                                    ['index' => 'value', 'label' => 'Value'],
                                    ['index' => 'module', 'label' => 'Module'],
                                    ['index' => 'action'],
                                ]"
                                     :rows="$translations"
                            >
                                @interact('column_value', $row)
                                    @if($this->editingKey === $row['key'])
                                        <div class="flex items-center space-x-2 min-w-0">
                                            <div class="flex-1 min-w-0">
                                                <textarea
                                                    wire:model.live="editingValue"
                                                    wire:keydown.enter="saveEdit"
                                                    wire:keydown.escape="cancelEdit"
                                                    wire:keydown.ctrl.enter="saveEdit"
                                                    rows="2"
                                                    class="w-full text-sm rounded-md border-violet-300 dark:border-violet-600 dark:bg-slate-700 dark:text-slate-300 focus:border-violet-500 focus:ring-violet-500 resize-none"
                                                    placeholder="Enter translation..."
                                                    x-data
                                                    x-init="$el.focus(); $el.select()"
                                                ></textarea>
                                            </div>
                                            <div class="flex items-center space-x-1 flex-shrink-0">
                                                <button
                                                    wire:click="saveEdit"
                                                    type="button"
                                                    class="inline-flex items-center p-1.5 text-green-600 hover:text-green-800 hover:bg-green-50 dark:hover:bg-green-900/20 rounded transition-colors"
                                                    title="Save (Enter or Ctrl+Enter)"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </button>
                                                <button
                                                    wire:click="cancelEdit"
                                                    type="button"
                                                    class="inline-flex items-center p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                                                    title="Cancel (Escape)"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-between group min-w-0">
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm text-slate-900 dark:text-slate-100 break-words">
                                                    {{ $row['value'] ?: '-' }}
                                                </span>
                                            </div>
                                            <button
                                                wire:click="startEdit('{{ $row['key'] }}', '{{ addslashes($row['value']) }}')"
                                                type="button"
                                                class="ml-2 p-1.5 text-slate-400 hover:text-violet-600 hover:bg-violet-50 dark:hover:bg-violet-900/20 rounded opacity-0 group-hover:opacity-100 transition-all flex-shrink-0"
                                                title="Edit translation"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @endif
                                @endinteract

                                @interact('column_action', $row)
                                    @if($this->editingKey === $row['key'])
                                        <div class="flex items-center space-x-1">
                                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                                Enter/Ctrl+Enter to save, Esc to cancel
                                            </span>
                                        </div>
                                    @else
                                        <x-button
                                            wire:click="startEdit('{{ $row['key'] }}', '{{ addslashes($row['value']) }}')"
                                            icon="pencil-square"
                                            text="Edit"
                                            color="violet"
                                            size="sm"
                                            flat
                                        />
                                    @endif
                                @endinteract
                            </x-table>
                        </div>
                    @elseif ($activeLanguage && blank($translations) && !$isInitializing)
                        <div class="text-center py-12">
                            <div class="text-slate-400 dark:text-slate-500 mb-4">
                                <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100 mb-2">
                                No translations found
                            </h3>
                            <p class="text-slate-500 dark:text-slate-400 mb-4">
                                No translations found for {{ strtoupper($activeLanguage) }}. Use the Auto Translate button to generate translations.
                            </p>
                            <x-button
                                color="violet"
                                wire:click="translateMissingStrings"
                                :loading="$isTranslating"
                                icon="sparkles"
                            >
                                {{ $isTranslating ? 'Translating...' : 'Start Auto Translation' }}
                            </x-button>
                        </div>
                    @endif
                </x-tab>
            </div>
        @elseif (!$isInitializing)
            <x-card class="mb-6">
                <div class="text-center py-8">
                    <div class="text-slate-400 dark:text-slate-500 mb-4">
                        <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100 mb-2">
                        No language files found
                    </h3>
                    <p class="text-slate-500 dark:text-slate-400 mb-4">
                        Click "Refresh Strings" to scan your application for translatable strings and create initial language files.
                    </p>
                    <x-button
                        color="violet"
                        wire:click="refreshStrings"
                        :loading="$isInitializing"
                        icon="magnifying-glass"
                    >
                        {{ $isInitializing ? 'Scanning...' : 'Scan for Strings' }}
                    </x-button>
                </div>
            </x-card>
        @endif
    </div>
</div>
