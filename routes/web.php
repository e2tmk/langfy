<?php

declare(strict_types = 1);

namespace Routes;

use Illuminate\Support\Facades\Route;
use Langfy\Livewire\LangfyPage;

Route::middleware(['web'])->group(function () {
    Route::get('/langfy', LangfyPage::class);
});
