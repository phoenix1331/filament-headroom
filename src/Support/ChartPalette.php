<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Support;

use Illuminate\Support\Js;

/**
 * Categorical series colours for every Headroom chart, validated for colour
 * vision deficiency separation on Filament's light (#ffffff) and dark
 * (#18181b) widget surfaces. Charts pick the mode at draw time, so a theme
 * switch repaints them. Slots are fixed: a seventh series folds into "Other".
 */
final class ChartPalette
{
    /** @var list<string> */
    public const array LIGHT = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#6250d6'];

    /** @var list<string> */
    public const array DARK = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#9085e9'];

    public const string OTHER_LIGHT = '#9ca3af';

    public const string OTHER_DARK = '#71717a';

    /** Filament's widget surfaces, used for the gap between stacked segments. */
    public const string SURFACE_LIGHT = '#ffffff';

    public const string SURFACE_DARK = '#18181b';

    public static function slots(): int
    {
        return count(self::LIGHT);
    }

    /**
     * Colour follows the series, not its rank: slots go in name order, so a
     * series keeps its colour while the set of drawn series is unchanged.
     *
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    public static function slotsFor(array $keys): array
    {
        $sorted = $keys;
        sort($sorted, SORT_NATURAL);

        return array_flip($sorted);
    }

    /**
     * A Chart.js scriptable colour for the chart surface in the current mode.
     */
    public static function surface(): string
    {
        return sprintf(
            '() => document.documentElement.classList.contains(\'dark\') ? %s : %s',
            Js::from(self::SURFACE_DARK),
            Js::from(self::SURFACE_LIGHT),
        );
    }

    /**
     * A Chart.js scriptable colour reading the dataset's `slot` (null for "Other").
     */
    public static function scriptable(): string
    {
        return sprintf(
            '(context) => { const dark = document.documentElement.classList.contains(\'dark\'); const slot = context.dataset.slot; return slot === null || slot === undefined ? (dark ? %s : %s) : (dark ? %s : %s)[slot]; }',
            Js::from(self::OTHER_DARK),
            Js::from(self::OTHER_LIGHT),
            Js::from(self::DARK),
            Js::from(self::LIGHT),
        );
    }
}
