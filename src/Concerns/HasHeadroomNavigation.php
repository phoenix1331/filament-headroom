<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Concerns;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use UnitEnum;

/**
 * For Headroom pages: navigation group, icon and sort come from the plugin
 * options. The page's own $navigationSort is its position within the block.
 */
trait HasHeadroomNavigation
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $plugin = HeadroomPlugin::current();

        if ($plugin === null) {
            return parent::getNavigationGroup();
        }

        return $plugin->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return HeadroomPlugin::current()?->getNavigationIcon(static::class) ?? parent::getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        $base = HeadroomPlugin::current()?->getNavigationSort();

        if ($base === null) {
            return parent::getNavigationSort();
        }

        return $base + (parent::getNavigationSort() ?? 0);
    }
}
