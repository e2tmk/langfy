<?php

declare(strict_types = 1);

namespace Langfy\Services;

use Illuminate\Support\Collection;

class StringFinder
{
    protected Collection $paths;

    protected array $defaultIgnorePaths = [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap',
        'public',
    ];

    public function __construct()
    {
        $this->paths              = collect([]);
        $this->defaultIgnorePaths = array_merge($this->defaultIgnorePaths, config('langfy.finder.ignore_paths', []));
    }

    public static function in(string | array $paths): self
    {
        $instance = new self;

        if (is_string($paths)) {
            $paths = [$paths];
        }

        $instance->paths = collect($paths);

        return $instance;
    }

    public function and(string | array $paths): self
    {
        if (is_string($paths)) {
            $paths = [$paths];
        }

        $this->paths = $this->paths->merge($paths);

        return $this;
    }

    public function ignore(string | array $paths): self
    {
        if (is_string($paths)) {
            $paths = [$paths];
        }

        $this->defaultIgnorePaths = array_merge($this->defaultIgnorePaths, $paths);

        return $this;
    }
}
