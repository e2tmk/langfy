<div class="min-h-screen bg-slate-50 dark:bg-slate-900">
    <div class="container mx-auto px-4 py-8">
        <x-toast />

        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="70" height="70" viewBox="0 0 24 24" class="text-dark-950 dark:text-white">
                            <path fill="currentColor" d="M19 16v1h-5v-1h1v-2h3v2zm3-11v1h-1v1h-1v1h-2V7h-1V6h-1V5h2V4h-1V3h-1V2h3v1h1v2z"/>
                            <path fill="currentColor" d="M22 10V9H11v1h-1v12h1v1h11v-1h1V10zm-1 11h-2v-3h-5v3h-2v-5h1v-2h1v-1h1v-1h1v-1h1v1h1v1h1v1h1v2h1zM7 7v1H6v1H4V7z"/>
                            <path fill="currentColor" d="M14 2v6h-2V6h-1V5H9v1H8V4h4V3H3v1h4v2H6V5H4v1H3v4h1v1h2v-1h1v3h1V8h1V7h2v1h-1v1H9v6H2v-1H1V2h1V1h11v1zM6 19v1h1v1h1v1H5v-1H4v-2H2v-1h1v-1h1v-1h2v1h1v1h1v1z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">
                            Langfy Translation Management
                        </h1>
                        <p class="text-slate-600 dark:text-slate-400">
                            Manage and translate your application strings with AI-powered assistance
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <x-button
                        color="slate"
                        wire:click="refreshStrings"
                        :loading="$isInitializing"
                        icon="arrow-path"
                        size="sm"
                    >
                        Refresh
                    </x-button>
                    <x-button
                        color="violet"
                        wire:click="translateMissingStrings(true)"
                        :loading="$isTranslating"
                        icon="sparkles"
                    >
                        {{ $isTranslating ? 'Translating...' : 'Auto Translate Missing' }}
                    </x-button>
                </div>
            </div>
        </div>

{{--        <!-- Context and Module Selection -->--}}
{{--        <div class="mb-6">--}}
{{--            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">--}}
{{--                <div class="flex items-center space-x-4">--}}
{{--                    <!-- Context Selection -->--}}
{{--                    <div class="flex-1">--}}
{{--                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">--}}
{{--                            Context--}}
{{--                        </label>--}}
{{--                        <select wire:model.live="activeContext" class="w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">--}}
{{--                            <option value="application">Application</option>--}}
{{--                            @if(filled($availableModules))--}}
{{--                                <option value="module">Module</option>--}}
{{--                            @endif--}}
{{--                        </select>--}}
{{--                    </div>--}}

{{--                    <!-- Module Selection (only shown when context is module) -->--}}
{{--                    @if($activeContext === 'module' && filled($availableModules))--}}
{{--                        <div class="flex-1">--}}
{{--                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">--}}
{{--                                Module--}}
{{--                            </label>--}}
{{--                            <select wire:model.live="activeModule" class="w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">--}}
{{--                                @foreach($availableModules as $module)--}}
{{--                                    <option value="{{ $module }}">{{ $module }}</option>--}}
{{--                                @endforeach--}}
{{--                            </select>--}}
{{--                        </div>--}}
{{--                    @endif--}}

{{--                    <!-- Current Selection Display -->--}}
{{--                    <div class="flex-1">--}}
{{--                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">--}}
{{--                            Current Target--}}
{{--                        </label>--}}
{{--                        <div class="px-3 py-2 bg-slate-50 dark:bg-slate-700 rounded-md border border-slate-200 dark:border-slate-600">--}}
{{--                            <span class="text-sm font-medium text-slate-900 dark:text-slate-100">--}}
{{--                                @if($activeContext === 'application')--}}
{{--                                    Application--}}
{{--                                @else--}}
{{--                                    Module: {{ $activeModule }}--}}
{{--                                @endif--}}
{{--                            </span>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}

        <div>
            <x-progress :percent="50" />
        </div>

        <!-- Language Tabs -->
        @if (filled($availableLanguages))
            <div class="my-8">
                <x-tab wire:model.live="activeLanguage">
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
                                                <x-input
                                                    wire:model.live="editingValue"
                                                    wire:keydown.enter="saveEdit"
                                                    wire:keydown.escape="cancelEdit"
                                                    wire:keydown.ctrl.enter="saveEdit"
                                                    x-data
                                                    x-init="$el.focus(); $el.select()"
                                                />
                                            </div>
                                            <div class="flex items-center space-x-1 flex-shrink-0">
                                                <x-button
                                                    wire:click="saveEdit"
                                                    icon="check"
                                                    color="green"
                                                    loading
                                                    flat
                                                    md
                                                />
                                                <x-button
                                                    wire:click="cancelEdit"
                                                    icon="x-mark"
                                                    color="red"
                                                    loading
                                                    flat
                                                    md
                                                />
                                            </div>
                                        </div>
                                    @else
                                        <div wire:click="startEdit('{{ $row['key'] }}', {{ json_encode($row['value']) }})" class="flex items-center justify-between group min-w-0">
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm text-slate-900 dark:text-slate-100 break-words">
                                                    {{ $row['value'] ?: '-' }}
                                                </span>
                                            </div>
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
                                            wire:click="startEdit('{{ $row['key'] }}', {{ json_encode($row['value']) }})"
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
                                <x-icon name="language" class="mx-auto h-10 w-10"/>
                            </div>
                            <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100 mb-2">
                                No translations found
                            </h3>
                            <p class="text-slate-500 dark:text-slate-400 mb-4">
                                No translations found for <x-badge text="{{strtoupper($activeLanguage)}}" color="violet" outline sm/>. Use the Auto Translate button to generate translations.
                            </p>
                            <x-button
                                color="violet"
                                wire:click="translateMissingStrings(false)"
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
