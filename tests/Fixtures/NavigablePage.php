<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Tests\Fixtures;

use Filament\Pages\Page;
use Phoenix1331\FilamentHeadroom\Concerns\HasHeadroomNavigation;

class NavigablePage extends Page
{
    use HasHeadroomNavigation;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament-panels::pages.page';
}
