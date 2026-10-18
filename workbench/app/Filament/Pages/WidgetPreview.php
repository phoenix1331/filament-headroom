<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Filament\Pages\Dashboard;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\Widget;
use Livewire\Attributes\Url;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

/**
 * One Headroom widget, full width, without the panel chrome, so the
 * screenshot script can capture each widget on its own. Workbench only.
 */
class WidgetPreview extends Dashboard
{
    protected static string $routePath = 'widget-preview';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public string $widget = '';

    public static function hideChrome(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<style>.fi-sidebar, .fi-topbar, .fi-header, .fi-sidebar-close-overlay { display: none !important; } .fi-main-ctn { padding-top: 0 !important; } .fi-main { padding-block: 1rem !important; }</style>',
            scopes: static::class,
        );
    }

    public function getColumns(): int
    {
        return 1;
    }

    /**
     * @return list<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return array_values(array_filter(
            HeadroomPlugin::WIDGETS,
            fn (string $widget): bool => str($widget)->classBasename()->kebab()->toString() === $this->widget,
        ));
    }
}
