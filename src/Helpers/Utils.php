<?php

declare(strict_types = 1);

namespace Langfy\Helpers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Facades\Module;

class Utils
{
    /**
     * Save an array of strings to a JSON file.
     *
     * @param  array<string> | array<string, string>  $strings  The strings to save.
     * @param  string  $filePath  The path to the JSON file where the strings will be saved.
     *
     * @throws FileNotFoundException
     */
    public function saveStringsToFile(array $strings, string $filePath): void
    {
        // Ensure the directory and file exist
        File::ensureDirectoryExists(dirname($filePath));

        if (! File::exists($filePath)) {
            File::put($filePath, '');
        }

        $normalizedStrings = self::normalizeStringsArray($strings);

        $mergedTranslations = collect(json_decode(File::get($filePath), true) ?? [])
            ->merge($normalizedStrings)
            ->toArray();

        File::put($filePath, json_encode($mergedTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function laravelModulesEnabled(): bool
    {
        $modulesDir = config('modules.paths.modules');

        return filled($modulesDir) && File::isDirectory($modulesDir);
    }

    public function availableModules(): array
    {
        if (! self::laravelModulesEnabled()) {
            return [];
        }

        return collect(Module::getOrdered())->map(fn ($module) => $module->getName())->toArray();
    }

    public function modulePath(string $moduleName): ?string
    {
        if (! self::laravelModulesEnabled()) {
            return null;
        }

        $module = Module::find($moduleName);

        return optional($module)->getPath();
    }

    public function normalizeStringsArray(array $strings): array
    {
        // If all keys are strings, return as is
        if (collect($strings)->keys()->every(fn ($key): bool => is_string($key))) {
            return $strings;
        }

        // If keys are not strings, convert them to strings
        return collect($strings)
            ->mapWithKeys(fn ($string) => [$string => $string])
            ->toArray();
    }

    public function getDefaultApplicationPaths(): array
    {
        return [
            base_path('app'),
            base_path('resources'),
            base_path('routes'),
            base_path('config'),
            base_path('database'),
        ];
    }

    public static function __callStatic(string $method, array $args)
    {
        if (method_exists(self::class, $method)) {
            return self::$method(...$args);
        }

        $instance = new self;

        if (method_exists($instance, $method)) {
            return $instance->$method(...$args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist in " . self::class);
    }
}
