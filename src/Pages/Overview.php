<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

/**
 * The Headroom dashboard: the range picker and every enabled widget.
 */
class Overview extends HeadroomPage
{
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
