<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Pages;

use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesPageAccess;
use Phoenix1331\FilamentHeadroom\Concerns\HasHeadroomNavigation;
use Phoenix1331\FilamentHeadroom\Support\RangeSelect;

/**
 * A Headroom page: access and navigation from the plugin, and the range
 * picker whose value every widget on the page reads. Built on Filament's
 * Dashboard page for its filters form and widget grid, at its own path so
 * the panel's own dashboard is untouched.
 */
abstract class HeadroomPage extends Dashboard
{
    use AuthorisesPageAccess;
    use HasFiltersForm;
    use HasHeadroomNavigation;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([RangeSelect::make()]);
    }
}
