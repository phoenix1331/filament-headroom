<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Tests\Fixtures;

use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Support\RangeSelect;

/**
 * A host app's own dashboard placing every Headroom widget, with the range
 * picker in its filters form.
 */
class HostDashboard extends Dashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([RangeSelect::make()]);
    }

    public function getWidgets(): array
    {
        return HeadroomPlugin::WIDGETS;
    }
}
