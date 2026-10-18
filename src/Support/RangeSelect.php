<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Support;

use Filament\Forms\Components\Select;
use Phoenix1331\FilamentHeadroom\Enums\Range;

/**
 * The range picker Headroom widgets read. Add it to any dashboard's filters
 * form (HasFiltersForm) and every Headroom widget on that page follows it:
 *
 *     public function filtersForm(Schema $schema): Schema
 *     {
 *         return $schema->components([RangeSelect::make()]);
 *     }
 */
final class RangeSelect
{
    /** The page filter key Headroom widgets read the range from. */
    public const string NAME = 'range';

    public static function make(): Select
    {
        return Select::make(self::NAME)
            ->label(__('filament-headroom::ranges.label'))
            ->options(fn (): array => Range::options())
            ->default(fn (): string => Range::resolve(null)->value)
            ->selectablePlaceholder(false);
    }
}
