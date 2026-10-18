<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Support;

use Illuminate\Support\Number;

/**
 * Locale-aware numbers for pages and widgets, with a plain fallback where
 * intl cannot format (Number returns false).
 */
final class Format
{
    public static function number(int|float $value): string
    {
        $formatted = Number::format($value, maxPrecision: 1, locale: app()->getLocale());

        return $formatted === false ? (string) $value : $formatted;
    }

    /**
     * A percentage, or "No data" when there is nothing to average.
     */
    public static function percent(?float $percent): string
    {
        if ($percent === null) {
            return __('filament-headroom::widgets.no_data');
        }

        $formatted = Number::percentage($percent, maxPrecision: 1, locale: app()->getLocale());

        return $formatted === false ? round($percent, 1).'%' : $formatted;
    }
}
