<?php

declare(strict_types = 1);

namespace Langfy;

use Langfy\Console\Commands\FinderCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LangfyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('langfy')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                FinderCommand::class,
            ]);
    }
}
