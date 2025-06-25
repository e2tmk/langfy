<?php

declare(strict_types = 1);

use Langfy\Services\Finder;

if (! function_exists('langfy_finder')) {
    /**
     * Create a new Finder instance with the specified paths.
     */
    function langfy_finder(string | array $paths): Finder
    {
        return Finder::in($paths);
    }
}
