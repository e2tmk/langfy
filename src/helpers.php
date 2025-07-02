<?php

declare(strict_types = 1);

use Langfy\Enums\Context;
use Langfy\Langfy;

if (! function_exists('langfy')) {
    /**
     * Create a new Langfy instance for the specified context.
     */
    function langfy(Context $context, ?string $moduleName = null): Langfy
    {
        return Langfy::for($context, $moduleName);
    }
}

if (! function_exists('itrans')) {
    /**
     * Translate the given message but ignore it during string discovery.
     *
     * This function works exactly like __() but is ignored by Langfy's finder.
     * Use this when you want to translate a string but don't want it to be
     * automatically discovered during the scanning process.
     *
     * @param  string  $key
     * @param  array  $replace
     * @param  string|null  $locale
     * @return string
     */
    function itrans(string $key, array $replace = [], ?string $locale = null): string
    {
        return __($key, $replace, $locale);
    }
}
