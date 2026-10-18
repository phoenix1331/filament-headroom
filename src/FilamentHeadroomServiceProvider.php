<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom;

use Illuminate\Support\ServiceProvider;

class FilamentHeadroomServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-headroom');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-headroom');
    }
}
