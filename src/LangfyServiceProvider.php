<?php

declare(strict_types = 1);

namespace Langfy;

use Langfy\Console\Commands\FinderCommand;
use Langfy\Console\Commands\TransCommand;
use Langfy\Console\Commands\TranslateChunkCommand;
use Langfy\Livewire\LangfyView;
use Livewire\Livewire;
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
            ->hasTranslations()
            ->hasAssets()
            ->hasRoutes(['web'])
            ->hasCommands([
                FinderCommand::class,
                TransCommand::class,
                TranslateChunkCommand::class,
            ]);
    }

    public function bootingPackage(): void
    {
        Livewire::component('langfy.livewire.langfy-view', LangfyView::class);
    }
}
