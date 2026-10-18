<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Phoenix1331\AiHeadroomLedger\Data\Breakdown;
use Phoenix1331\AiHeadroomLedger\Data\Group;
use Phoenix1331\AiHeadroomLedger\Data\Money;
use Phoenix1331\AiHeadroomLedger\Data\Spend;
use Phoenix1331\AiHeadroomLedger\Data\SpendSummary;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;
use Phoenix1331\FilamentHeadroom\Concerns\QueriesLedger;
use Phoenix1331\FilamentHeadroom\Support\Cost;
use Phoenix1331\FilamentHeadroom\Support\Format;

/**
 * Spend with its trend against the previous period, request count, average
 * context used, and blocked and warned counts for the selected range.
 */
class StatsOverview extends StatsOverviewWidget
{
    use AuthorisesWidgetView;
    use QueriesLedger;

    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $spend = $this->ledger()->spend();
        $context = $this->ledger()->context();
        $daily = $this->ledger()->byDay()->spend();

        $contextStat = Stat::make(__('filament-headroom::widgets.stats.context'), Format::percent($context->averagePercentUsed()));

        if ($context->samples > 0) {
            $contextStat->description(__('filament-headroom::widgets.stats.context_peak', ['percent' => Format::percent($context->maxPercentUsed)]));
        }

        return [
            $this->spendStat($spend, $daily),
            Stat::make(__('filament-headroom::widgets.stats.requests'), Format::number($context->requests))
                ->description(__('filament-headroom::widgets.stats.requests_with_usage', ['count' => Format::number($spend->requestsWithUsage)]))
                ->chart(array_map(fn (Group $day): float => $day->value->requests * 1.0, $daily->all())),
            $contextStat,
            Stat::make(__('filament-headroom::widgets.stats.blocked'), Format::number($context->blocked))
                ->description(__('filament-headroom::widgets.stats.blocked_description'))
                ->color($context->blocked > 0 ? 'danger' : null),
            Stat::make(__('filament-headroom::widgets.stats.warned'), Format::number($context->warned))
                ->description(__('filament-headroom::widgets.stats.warned_description'))
                ->color($context->warned > 0 ? 'warning' : null),
        ];
    }

    /**
     * @param  Breakdown<SpendSummary>  $daily
     */
    private function spendStat(SpendSummary $spend, Breakdown $daily): Stat
    {
        $stat = Stat::make(__('filament-headroom::widgets.stats.spend'), Cost::fromSummary($spend));

        $currency = self::soleCurrency($spend->total);

        if ($currency === null) {
            return $stat;
        }

        $stat->chart(array_map(
            fn (Group $day): float => $day->value->total->in($currency)?->amount() ?? 0.0,
            $daily->all(),
        ));

        $previous = $this->ledgerBase()->between(...$this->range()->previous())->spend()->total;

        return $this->withTrend($stat, $spend->total->in($currency), $previous->in($currency));
    }

    /**
     * Compares the same currency only. Previous spend in other currencies
     * does not count, so a currency switch reads as new spend.
     */
    private function withTrend(Stat $stat, ?Money $current, ?Money $previous): Stat
    {
        $days = $this->range()->days();

        if ($previous === null || $previous->microUnits === 0) {
            return $stat->description(__('filament-headroom::widgets.stats.spend_trend_new', ['days' => $days]));
        }

        $change = (($current->microUnits ?? 0) - $previous->microUnits) / $previous->microUnits * 100;
        $percent = Format::percent(abs($change));

        if (round($change, 1) == 0.0) {
            return $stat->description(__('filament-headroom::widgets.stats.spend_trend_flat', ['days' => $days]));
        }

        if ($change > 0) {
            return $stat
                ->description(__('filament-headroom::widgets.stats.spend_trend_up', ['percent' => $percent, 'days' => $days]))
                ->descriptionIcon(Heroicon::ArrowTrendingUp)
                ->color('warning');
        }

        return $stat
            ->description(__('filament-headroom::widgets.stats.spend_trend_down', ['percent' => $percent, 'days' => $days]))
            ->descriptionIcon(Heroicon::ArrowTrendingDown)
            ->color('success');
    }

    /**
     * Trends and sparklines need one currency; amounts in different
     * currencies are never added together.
     */
    private static function soleCurrency(Spend $spend): ?string
    {
        $currencies = $spend->currencies();

        return count($currencies) === 1 ? $currencies[0] : null;
    }
}
