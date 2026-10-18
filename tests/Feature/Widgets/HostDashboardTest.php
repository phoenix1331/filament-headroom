<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Widgets\Widget;
use Illuminate\Auth\GenericUser;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\HostDashboard;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

dataset('widgets', fn (): array => array_combine(
    array_map(class_basename(...), HeadroomPlugin::WIDGETS),
    array_map(fn (string $widget): array => [$widget], HeadroomPlugin::WIDGETS),
));

function onHostPanel(string $environment): void
{
    app()->detectEnvironment(fn (): string => $environment);
    Filament::setCurrentPanel(Panel::make()->id('host'));
}

describe('panel without the plugin', function (): void {
    it('hides and refuses each widget outside local', function (string $widget): void {
        onHostPanel('production');

        /** @var class-string<Widget> $widget */
        expect($widget::canView())->toBeFalse();

        livewire($widget)->assertForbidden();
    })->with('widgets');

    it('shows each widget in local', function (string $widget): void {
        onHostPanel('local');

        /** @var class-string<Widget> $widget */
        expect($widget::canView())->toBeTrue();

        livewire($widget)->assertOk();
    })->with('widgets');
});

describe('panel with the plugin', function (): void {
    it('applies the plugin authorize() callback to each widget', function (string $widget): void {
        HeadroomPlugin::get()->authorize(fn ($user): bool => $user?->getAuthIdentifier() === 1);

        livewire($widget)->assertForbidden();

        $this->actingAs(new GenericUser(['id' => 1]));
        livewire($widget)->assertOk();
    })->with('widgets');

    it('refuses later requests once access is revoked', function (string $widget): void {
        HeadroomPlugin::get()->authorize(true);
        $component = livewire($widget)->assertOk();

        HeadroomPlugin::get()->authorize(false);

        $component->call('$refresh')->assertForbidden();
    })->with('widgets');

    it('hides widgets disabled through widgets()', function (string $widget): void {
        HeadroomPlugin::get()->authorize(true)->widgets(array_values(array_diff(HeadroomPlugin::WIDGETS, [$widget])));

        /** @var class-string<Widget> $widget */
        expect($widget::canView())->toBeFalse();
    })->with('widgets');
});

describe('host dashboard', function (): void {
    it('shows only the widgets the user may see', function (): void {
        $dashboard = livewire(HostDashboard::class)->instance();
        expect($dashboard->getVisibleWidgets())->toBe([]);

        HeadroomPlugin::get()->authorize(true)->widgets(array_slice(HeadroomPlugin::WIDGETS, 0, 2));
        expect(livewire(HostDashboard::class)->instance()->getVisibleWidgets())->toBe(array_slice(HeadroomPlugin::WIDGETS, 0, 2));
    });

    it('offers the range picker with the default range selected', function (): void {
        livewire(HostDashboard::class)
            ->assertSet('filters.range', '30d')
            ->assertSee('Last 7 days')
            ->assertSee('Last 90 days');
    });

    it('selects the plugin default range', function (): void {
        HeadroomPlugin::get()->defaultRange('90d');

        livewire(HostDashboard::class)->assertSet('filters.range', '90d');
    });

    it('keeps the chosen range under the key the widgets read', function (): void {
        // Filament hands a dashboard's `filters` to each widget as `pageFilters`;
        // each widget's own tests prove it follows `pageFilters.range`.
        livewire(HostDashboard::class)
            ->fillForm(['range' => '7d'], 'filtersForm')
            ->assertSet('filters', ['range' => '7d']);
    });
});
