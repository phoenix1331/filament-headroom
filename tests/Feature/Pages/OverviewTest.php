<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Overview;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\SpendOverTime;
use Phoenix1331\FilamentHeadroom\Widgets\StatsOverview;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

it('is one of the built-in pages, registered at its own path', function (): void {
    expect(HeadroomPlugin::PAGES)->toContain(Overview::class)
        ->and(Filament::getPanel('admin')->getPages())->toContain(Overview::class)
        ->and(Route::has(Overview::getRouteName()))->toBeTrue()
        ->and(Overview::getUrl(panel: 'admin'))->toEndWith('/admin/headroom');
});

it('leaves the panel dashboard route alone', function (): void {
    expect(Route::has('filament.admin.pages.dashboard'))->toBeFalse()
        ->and(Overview::getRouteName())->not->toBe('filament.admin.pages.dashboard');
});

it('refuses access by default outside local', function (): void {
    expect(Overview::canAccess())->toBeFalse();

    livewire(Overview::class)->assertForbidden();
});

it('opens for authorised users with the range picker', function (): void {
    HeadroomPlugin::get()->authorize(true);

    livewire(Overview::class)
        ->assertOk()
        ->assertSee('AI usage')
        ->assertSet('filters.range', '30d')
        ->assertSee('Last 90 days');
});

it('opens on the plugin default range', function (): void {
    HeadroomPlugin::get()->authorize(true)->defaultRange('7d');

    livewire(Overview::class)->assertSet('filters.range', '7d');
});

it('shows every enabled widget, in order', function (): void {
    HeadroomPlugin::get()->authorize(true);

    expect(livewire(Overview::class)->instance()->getVisibleWidgets())->toBe(HeadroomPlugin::get()->getWidgets())
        ->and(HeadroomPlugin::get()->getWidgets())->toContain(...HeadroomPlugin::WIDGETS);
});

it('leaves out disabled widgets', function (): void {
    HeadroomPlugin::get()->authorize(true)->widgets([SpendOverTime::class, StatsOverview::class]);

    expect(livewire(Overview::class)->instance()->getVisibleWidgets())->toBe([SpendOverTime::class, StatsOverview::class]);
});

it('takes its navigation from the plugin', function (): void {
    expect(Overview::getNavigationLabel())->toBe('Overview')
        ->and(Overview::getNavigationGroup())->toBe('Headroom')
        ->and(Overview::getNavigationIcon())->toBe(Heroicon::OutlinedChartBar)
        ->and(Overview::getNavigationSort())->toBe(0);

    HeadroomPlugin::get()->navigationGroup('Monitoring')->navigationSort(20)->navigationIcon('heroicon-o-cpu-chip', Overview::class);

    expect(Overview::getNavigationGroup())->toBe('Monitoring')
        ->and(Overview::getNavigationSort())->toBe(20)
        ->and(Overview::getNavigationIcon())->toBe('heroicon-o-cpu-chip');
});
