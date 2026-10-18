<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class FilamentHeadroomServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-headroom');
        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-headroom'),
        ], 'filament-headroom-translations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-headroom');

        // Published with `php artisan filament:assets`, like Filament's own.
        FilamentAsset::register([
            Css::make('filament-headroom', __DIR__.'/../resources/dist/filament-headroom.css'),
        ], package: 'phoenix1331/filament-headroom');
    }
}
