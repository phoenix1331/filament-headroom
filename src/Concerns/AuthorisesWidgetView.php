<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Concerns;

use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

/**
 * For Headroom widgets: dashboards skip them unless the plugin's authorize()
 * rule allows the user, including on host dashboards without the plugin,
 * where the local-only default applies.
 */
trait AuthorisesWidgetView
{
    public static function canView(): bool
    {
        return HeadroomPlugin::allows();
    }
}
