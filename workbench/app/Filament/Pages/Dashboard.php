<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Phoenix1331\FilamentHeadroom\Support\RangeSelect;

/**
 * The host app's own dashboard, showing how Headroom widgets follow a range
 * picker added to any dashboard's filters form.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([RangeSelect::make()]);
    }
}
