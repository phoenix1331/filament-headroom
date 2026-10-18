<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Phoenix1331\FilamentHeadroom\Widgets\ModelsTable;

/**
 * Per-model spend, context usage, estimate accuracy and calibration status.
 */
class Models extends HeadroomPage
{
    protected static string $routePath = 'headroom/models';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    public static function getNavigationLabel(): string
    {
        return __('filament-headroom::pages.models.navigation_label');
    }

    public function getTitle(): string
    {
        return __('filament-headroom::pages.models.title');
    }

    /**
     * @return list<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [ModelsTable::class];
    }
}
