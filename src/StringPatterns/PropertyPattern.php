<?php

declare(strict_types = 1);

namespace Langfy\StringPatterns;

class PropertyPattern extends Pattern
{
    public function getPatterns(): array
    {
        return [
            // Property with inline @trans annotation
            // Example: protected string $test = 'Hello' /** @trans */
            '/(?:public|protected|private)\s+(?:[a-zA-Z_|\\\\]+\s+)?\$\w+\s*=\s*[\'"](.+?)[\'"].*?\/\*\*\s*@trans\s*\*\//',

            // Property with @trans annotation above
            // Example: /** @trans */ \n public string $test = 'Hello'
            '/\/\*\*\s*@trans\s*\*\/.*?(?:public|protected|private)\s+(?:[a-zA-Z_|\\\\]+\s+)?\$\w+\s*=\s*[\'"](.+?)[\'"]/',

            // Property with #[Trans] attribute
            // Example: #[Trans] \n private string $test = 'Hello'
            '/#\[Trans\].*?(?:public|protected|private)\s+(?:[a-zA-Z_|\\\\]+\s+)?\$\w+\s*=\s*[\'"](.+?)[\'"]/',

            // Property with #[Trans()] attribute (with parentheses)
            '/#\[Trans\(\)\].*?(?:public|protected|private)\s+(?:[a-zA-Z_|\\\\]+\s+)?\$\w+\s*=\s*[\'"](.+?)[\'"]/',

            // Attribute full declaration (\Langfy\Trans)
            '/#\[ModuleManager\\\\Trans\].*?(?:public|protected|private)\s+(?:[a-zA-Z_|\\\\]+\s+)?\$\w+\s*=\s*[\'"](.+?)[\'"]/',

            // Attribute full declaration with parentheses
            '/#\[ModuleManager\\\\Trans\(\)\].*?(?:public|protected|private)\s+(?:[a-zA-Z_|\\\\]+\s+)?\$\w+\s*=\s*[\'"](.+?)[\'"]/',
        ];
    }
}
