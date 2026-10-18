<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(HeadroomPlugin::make());
    }
}
