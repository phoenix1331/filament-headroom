<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesPageAccess;
use Phoenix1331\FilamentHeadroom\Concerns\HasHeadroomNavigation;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Support\RangeSelect;

/**
 * The Headroom dashboard: the range picker and every enabled widget. Built on
 * Filament's Dashboard page for its filters form and widget grid, at its own
 * path so the panel's own dashboard is untouched.
 */
class Overview extends Dashboard
{
    use AuthorisesPageAccess;
    use HasFiltersForm;
    use HasHeadroomNavigation;

    protected static string $routePath = 'headroom';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function getNavigationLabel(): string
    {
        return __('filament-headroom::pages.overview.navigation_label');
    }

    public function getTitle(): string
    {
        return __('filament-headroom::pages.overview.title');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([RangeSelect::make()]);
    }

    /**
     * The widgets enabled on this panel, in the plugin's order.
     *
     * @return list<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return HeadroomPlugin::current()?->getWidgets() ?? HeadroomPlugin::WIDGETS;
    }
}
