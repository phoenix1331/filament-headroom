<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Concerns;

use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

/**
 * For Headroom widgets: dashboards skip them unless the plugin's authorize()
 * rule allows the user and the widget is enabled through widgets(). On host
 * dashboards without the plugin, the local-only default applies and every
 * widget counts as enabled.
 */
trait AuthorisesWidgetView
{
    public static function canView(): bool
    {
        $enabled = HeadroomPlugin::current()?->hasWidget(static::class) ?? true;

        return $enabled && HeadroomPlugin::allows();
    }
}
