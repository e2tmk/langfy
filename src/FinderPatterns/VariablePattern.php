<?php

declare(strict_types = 1);

namespace Langfy\FinderPatterns;

class VariablePattern extends Pattern
{
    public function getPatterns(): array
    {
        return [
            // Variable with inline @trans annotation after the string
            // Example: $test = 'Test' /** @trans */
            '/\$\w+\s*=\s*[\'"](.+?)[\'"].*?\/\*\*\s*@trans\s*\*\//',

            // Variable with @trans annotation before the string
            // Example: $test = /** @trans */ 'Test'
            '/\$\w+\s*=\s*\/\*\*\s*@trans\s*\*\/\s*[\'"](.+?)[\'"]/',
        ];
    }
}
