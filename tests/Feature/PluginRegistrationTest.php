<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

it('registers the plugin on the panel', function (): void {
    expect(Filament::getPanel('admin')->hasPlugin(HeadroomPlugin::ID))->toBeTrue();
});

it('resolves the registered plugin instance', function (): void {
    expect(HeadroomPlugin::get())
        ->toBeInstanceOf(HeadroomPlugin::class)
        ->getId()->toBe('headroom');
});

it('makes a fresh plugin instance', function (): void {
    expect(HeadroomPlugin::make())->toBeInstanceOf(HeadroomPlugin::class);
});

it('rejects a container binding that is not the plugin', function (): void {
    app()->bind(HeadroomPlugin::class, fn (): stdClass => new stdClass);

    HeadroomPlugin::make();
})->throws(LogicException::class, 'Plugin [headroom] is not an instance of');
