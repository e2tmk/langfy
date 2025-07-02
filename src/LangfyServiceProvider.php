<?php

declare(strict_types = 1);

namespace Langfy;

use Langfy\Console\Commands\FinderCommand;
use Langfy\Console\Commands\TransCommand;
use Langfy\Console\Commands\TranslateChunkCommand;
use Langfy\Livewire\LangfyView;
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
            ->hasAssets()
            ->hasRoutes(['web'])
            ->hasViewComponent('langfy', LangfyView::class)
            ->hasCommands([
                FinderCommand::class,
                TransCommand::class,
                TranslateChunkCommand::class,
            ]);
    }
}
