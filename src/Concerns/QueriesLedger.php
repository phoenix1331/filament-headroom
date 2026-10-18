<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Phoenix1331\AiHeadroomLedger\Query\LedgerQuery;
use Phoenix1331\FilamentHeadroom\Enums\Range;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Support\RangeSelect;

/**
 * For Headroom widgets: the range comes from the page's `range` filter (the
 * Overview page, or a host dashboard that defines one), else the plugin
 * default. Every ledger read starts from ledger(), so scoping added there
 * applies to every widget.
 */
trait QueriesLedger
{
    use InteractsWithPageFilters;

    protected function range(): Range
    {
        return Range::resolve($this->pageFilters[RangeSelect::NAME] ?? null);
    }

    /**
     * Unfiltered by range, for comparisons with other periods.
     */
    protected function ledgerBase(): LedgerQuery
    {
        return HeadroomPlugin::ledger();
    }

    protected function ledger(): LedgerQuery
    {
        return $this->range()->apply($this->ledgerBase());
    }
}
