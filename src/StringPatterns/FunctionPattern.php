<?php

declare(strict_types = 1);

namespace Langfy\StringPatterns;

class FunctionPattern extends Pattern
{
    public function getPatterns(): array
    {
        return [
            // __('string') pattern
            '/__\([\'"](.+?)[\'"]\)/',

            // trans('string') pattern
            '/trans\([\'"](.+?)[\'"]\)/',

            // @lang('string') pattern
            '/@lang\([\'"](.+?)[\'"]\)/',

            // __('string', []) pattern (with parameters)
            '/__\([\'"](.+?)[\'"]\s*,/',

            // trans('string', []) pattern (with parameters)
            '/trans\([\'"](.+?)[\'"]\s*,/',

            // @lang('string', []) pattern (with parameters)
            '/@lang\([\'"](.+?)[\'"]\s*,/',
        ];
    }
}
