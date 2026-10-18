<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;

it('registers the plugin stylesheet as a Filament asset', function (): void {
    $styles = FilamentAsset::getStyles(['phoenix1331/filament-headroom']);

    expect($styles)->toHaveCount(1)
        ->and($styles[0]->getId())->toBe('filament-headroom')
        ->and(file_exists($styles[0]->getPath()))->toBeTrue();
});

it('styles only its own fi-headroom classes', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/dist/filament-headroom.css');

    preg_match_all('/^\s*(?:\.dark\s+)?\.([a-z0-9-]+)/m', (string) $css, $matches);

    expect(array_unique($matches[1]))->each->toStartWith('fi-headroom-');
});
