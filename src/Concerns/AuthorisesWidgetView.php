<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Concerns;

use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

/**
 * For Headroom widgets: dashboards skip them unless the plugin's authorize()
 * rule allows the user and the widget is enabled through widgets(). On host
 * dashboards without the plugin, the local-only default applies and every
 * widget counts as enabled.
 *
 * The widget also returns 403 on mount and on every Livewire request when
 * canView() is false, so its data is protected even when mounted directly.
 * Newer Filament releases check this on mount themselves; older Filament 5
 * releases do not, so it is enforced here.
 */
trait AuthorisesWidgetView
{
    public static function canView(): bool
    {
        $enabled = HeadroomPlugin::current()?->hasWidget(static::class) ?? true;

        return $enabled && HeadroomPlugin::allows();
    }

    public function mountAuthorisesWidgetView(): void
    {
        abort_unless(static::canView(), 403);
    }

    public function hydrateAuthorisesWidgetView(): void
    {
        abort_unless(static::canView(), 403);
    }
}
