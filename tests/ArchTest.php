<?php

declare(strict_types = 1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'ds', 'dumper', 'var_dump'])
    ->each->not->toBeUsed();

arch('Console Commands should extend Command class')
    ->expect('Langfy\Commands')
    ->toBeClasses()
    ->toExtend('Illuminate\Console\Command')
    ->toOnlyBeUsedIn('Langfy\LangfyServiceProvider');

arch('Finder Patterns should extend Langfy\Pattern')
    ->expect('Langfy\Finder\Patterns')
    ->toBeClasses()
    ->ignoring('Pattern')
    ->toExtend('Langfy\Pattern')
    ->toHaveMethod('getPatterns')
    ->toOnlyBeUsedIn('Langfy\Service\Finder');
