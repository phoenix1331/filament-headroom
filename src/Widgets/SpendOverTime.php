<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Widgets;

use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Js;
use Phoenix1331\AiHeadroomLedger\Data\Breakdown;
use Phoenix1331\AiHeadroomLedger\Data\Group;
use Phoenix1331\AiHeadroomLedger\Data\Money;
use Phoenix1331\AiHeadroomLedger\Data\SpendSummary;
use Phoenix1331\AiHeadroomLedger\Query\GroupedQuery;
use Phoenix1331\AiHeadroomLedger\Query\LedgerQuery;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;
use Phoenix1331\FilamentHeadroom\Concerns\QueriesLedger;
use Phoenix1331\FilamentHeadroom\Support\ChartPalette;

/**
 * Daily spend per model or provider. The top series by spend get a colour
 * each; the rest fold into "Other", computed from the daily total so the
 * lines always add up. One currency is drawn; others are named, never
 * converted.
 */
class SpendOverTime extends ChartWidget
{
    use AuthorisesWidgetView;
    use QueriesLedger;

    public const string BY_MODEL = 'model';

    public const string BY_PROVIDER = 'provider';

    public ?string $filter = self::BY_MODEL;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    /** Currency of the drawn series, set while building the data. */
    protected ?string $currency = null;

    /** @var list<string> */
    protected array $otherCurrencies = [];

    protected bool $includesEstimates = false;

    public function getHeading(): string
    {
        return __('filament-headroom::widgets.spend_over_time.heading');
    }

    public function getDescription(): ?string
    {
        $this->getCachedData();

        $lines = [];

        if ($this->currency === null) {
            return __('filament-headroom::widgets.spend_over_time.empty');
        }

        if ($this->includesEstimates) {
            $lines[] = __('filament-headroom::widgets.spend_over_time.includes_estimates');
        }

        if ($this->otherCurrencies !== []) {
            $lines[] = __('filament-headroom::widgets.spend_over_time.other_currencies', [
                'currency' => $this->currency,
                'others' => implode(', ', $this->otherCurrencies),
            ]);
        }

        return $lines === [] ? null : implode(' ', $lines);
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): array
    {
        return [
            self::BY_MODEL => __('filament-headroom::widgets.spend_over_time.by_model'),
            self::BY_PROVIDER => __('filament-headroom::widgets.spend_over_time.by_provider'),
        ];
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function getData(): array
    {
        $total = $this->ledger()->spend()->total;
        $days = $this->ledger()->byDay()->spend();
        $labels = array_map(
            fn (Group $day): string => CarbonImmutable::parse($day->key ?? 'today')->translatedFormat('j M'),
            $days->all(),
        );

        $currency = self::largestCurrency($total->all());

        if ($currency === null) {
            return ['labels' => $labels, 'datasets' => []];
        }

        $this->currency = $currency;
        $this->otherCurrencies = array_values(array_diff($total->currencies(), [$currency]));

        $groups = $this->grouped($this->ledger())->spend();
        $top = $this->topKeys($groups, $currency);
        $slots = self::slotsFor($top);

        $datasets = [];
        $drawn = array_fill(0, count($days), 0);

        foreach ($top as $key) {
            $series = $this->daily($this->filtered($this->ledger(), $key)->byDay()->spend(), $currency);

            foreach ($series as $index => $microUnits) {
                $drawn[$index] += $microUnits;
            }

            $summary = $groups->get($key);
            $estimated = $summary instanceof SpendSummary && $summary->isEstimated();
            $this->includesEstimates = $this->includesEstimates || $estimated;

            $datasets[] = self::dataset($key, $series, $slots[$key], $estimated);
        }

        $other = [];

        foreach ($this->daily($days, $currency) as $index => $microUnits) {
            $other[] = max(0, $microUnits - $drawn[$index]);
        }

        if (array_sum($other) > 0) {
            $estimated = $this->restIsEstimated($groups, $top);
            $this->includesEstimates = $this->includesEstimates || $estimated;

            $datasets[] = self::dataset(__('filament-headroom::widgets.spend_over_time.other'), $other, null, $estimated);
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    protected function getOptions(): RawJs
    {
        $this->getCachedData();

        // Filament renders these options inside an HTML attribute, so every
        // literal goes through Js::from(), which never emits a double quote.
        $locale = Js::from(str_replace('_', '-', app()->getLocale()));
        $currency = Js::from($this->currency ?? 'USD');
        $colour = ChartPalette::scriptable();
        $estimated = Js::from(' ('.mb_strtolower(__('filament-headroom::basis.includes_estimates.label')).')');

        return RawJs::make(<<<JS
            {
                datasets: {
                    line: {
                        borderColor: {$colour},
                        backgroundColor: {$colour},
                        pointBackgroundColor: {$colour},
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 12,
                        cubicInterpolationMode: 'monotone',
                    },
                },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': '
                                + new Intl.NumberFormat({$locale}, { style: 'currency', currency: {$currency}, maximumFractionDigits: context.parsed.y > 0 && context.parsed.y < 0.01 ? 4 : 2 }).format(context.parsed.y)
                                + (context.dataset.estimated ? {$estimated} : ''),
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 0, autoSkipPadding: 16 },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => new Intl.NumberFormat({$locale}, { style: 'currency', currency: {$currency}, maximumSignificantDigits: 3 }).format(value),
                        },
                    },
                },
            }
            JS);
    }

    protected function grouped(LedgerQuery $query): GroupedQuery
    {
        return $this->filter === self::BY_PROVIDER ? $query->byProvider() : $query->byModel();
    }

    protected function filtered(LedgerQuery $query, string $key): LedgerQuery
    {
        return $this->filter === self::BY_PROVIDER ? $query->forProvider($key) : $query->forModel($key);
    }

    /**
     * The groups with the most spend in the drawn currency, up to the palette size.
     * Requests without a model or provider are left to "Other".
     *
     * @param  Breakdown<SpendSummary>  $groups
     * @return list<string>
     */
    private function topKeys(Breakdown $groups, string $currency): array
    {
        $totals = [];

        foreach ($groups as $group) {
            $microUnits = $group->value->total->in($currency)->microUnits ?? 0;

            if ($group->key !== null && $microUnits > 0) {
                $totals[$group->key] = $microUnits;
            }
        }

        arsort($totals);

        return array_slice(array_map('strval', array_keys($totals)), 0, ChartPalette::slots());
    }

    /**
     * Colour follows the series, not its rank: slots go in name order, so a
     * model keeps its colour while the set of drawn series is unchanged.
     *
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private static function slotsFor(array $keys): array
    {
        $sorted = $keys;
        sort($sorted, SORT_NATURAL);

        return array_flip($sorted);
    }

    /**
     * @param  Breakdown<SpendSummary>  $groups
     * @param  list<string>  $top
     */
    private function restIsEstimated(Breakdown $groups, array $top): bool
    {
        foreach ($groups as $group) {
            if (! in_array($group->key, $top, true) && $group->value->isEstimated()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Micro-units per day in one currency, in day order.
     *
     * @param  Breakdown<SpendSummary>  $days
     * @return list<int>
     */
    private function daily(Breakdown $days, string $currency): array
    {
        return array_map(fn (Group $day): int => $day->value->total->in($currency)->microUnits ?? 0, $days->all());
    }

    /**
     * @param  list<int>  $microUnits
     * @return array<string, mixed>
     */
    private static function dataset(string $label, array $microUnits, ?int $slot, bool $estimated): array
    {
        return [
            'label' => $label,
            'data' => array_map(fn (int $value): float => $value / 1_000_000, $microUnits),
            'slot' => $slot,
            'estimated' => $estimated,
        ];
    }

    /**
     * @param  list<Money>  $amounts
     */
    private static function largestCurrency(array $amounts): ?string
    {
        $largest = null;

        foreach ($amounts as $money) {
            if ($largest === null || $money->microUnits > $largest->microUnits) {
                $largest = $money;
            }
        }

        return $largest?->currency;
    }
}
