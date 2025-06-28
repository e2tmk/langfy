<?php

declare(strict_types = 1);

namespace Routes;

use Illuminate\Support\Facades\Route;
use Langfy\Livewire\LangfyView;

Route::get('/langfy', LangfyView::class);
