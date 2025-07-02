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
                            <table class="w-full">
                                <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Key
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Value
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Module
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-slate-900 divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach ($translations as $key => $translation)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-mono text-slate-900 dark:text-slate-100 break-all">
                                                {{ $key }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
{{--                                            @if ($editingKey === $translation['key'])--}}
{{--                                                <div class="flex items-center space-x-2">--}}
{{--                                                    <x-input--}}
{{--                                                        wire:model="editingValue"--}}
{{--                                                        class="flex-1"--}}
{{--                                                        wire:keydown.enter="saveEdit"--}}
{{--                                                        wire:keydown.escape="cancelEdit"--}}
{{--                                                    />--}}
{{--                                                    <x-button--}}
{{--                                                        color="green"--}}
{{--                                                        size="sm"--}}
{{--                                                        icon="check"--}}
{{--                                                        wire:click="saveEdit"--}}
{{--                                                    />--}}
{{--                                                    <x-button--}}
{{--                                                        color="red"--}}
{{--                                                        size="sm"--}}
{{--                                                        icon="x-mark"--}}
{{--                                                        wire:click="cancelEdit"--}}
{{--                                                    />--}}
{{--                                                </div>--}}
{{--                                            @else--}}
{{--                                                <div class="text-sm text-slate-900 dark:text-slate-100">--}}
{{--                                                    {{ $translation }}--}}
{{--                                                </div>--}}
{{--                                            @endif--}}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
{{--                                            @if ($translation['module'])--}}
{{--                                                <x-badge color="violet" size="sm" :text="$translation['module']" />--}}
{{--                                            @else--}}
{{--                                                <span class="text-xs text-slate-400 dark:text-slate-500">App</span>--}}
{{--                                            @endif--}}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
{{--                                            @if ($editingKey !== $translation['key'])--}}
{{--                                                <x-button--}}
{{--                                                    color="slate"--}}
{{--                                                    size="sm"--}}
{{--                                                    icon="pencil"--}}
{{--                                                    wire:click="startEdit('{{ $translation['key'] }}', '{{ addslashes($translation['value']) }}')"--}}
{{--                                                >--}}
{{--                                                    Edit--}}
{{--                                                </x-button>--}}
{{--                                            @endif--}}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
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
