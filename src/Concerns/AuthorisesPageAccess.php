<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Concerns;

use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

/**
 * For Headroom pages: hides them from navigation and returns 403 on mount and
 * every Livewire request unless the plugin's authorize() rule allows the user.
 */
trait AuthorisesPageAccess
{
    public static function canAccess(): bool
    {
        return HeadroomPlugin::allows();
    }
}
