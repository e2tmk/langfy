<?php

declare(strict_types = 1);

namespace Langfy\FinderPatterns;

class FunctionPattern extends Pattern
{
    public function getPatterns(): array
    {
        return [
            // __('string') pattern - handles escaped quotes and nested structures
            '/__\(\'((?:[^\'\\\\]|\\\\.)*)\'(?:\s*,|\s*\))/s',
            '/__\("((?:[^"\\\\]|\\\\.)*)\"(?:\s*,|\s*\))/s',

            // trans('string') pattern - handles escaped quotes and nested structures
            '/trans\(\'((?:[^\'\\\\]|\\\\.)*)\'(?:\s*,|\s*\))/s',
            '/trans\("((?:[^"\\\\]|\\\\.)*)\"(?:\s*,|\s*\))/s',

            // @lang('string') pattern - handles escaped quotes and nested structures
            '/@lang\(\'((?:[^\'\\\\]|\\\\.)*)\'(?:\s*,|\s*\))/s',
            '/@lang\("((?:[^"\\\\]|\\\\.)*)\"(?:\s*,|\s*\))/s',
        ];
    }
}
