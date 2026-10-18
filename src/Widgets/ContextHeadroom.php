<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Js;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;
use Phoenix1331\FilamentHeadroom\Concerns\QueriesLedger;
use Phoenix1331\FilamentHeadroom\Support\ChartPalette;
use Phoenix1331\FilamentHeadroom\Support\Format;

/**
 * How much of their context window requests used, from pre-flight counts,
 * as a distribution. Requests over 100% did not fit and were blocked.
 */
class ContextHeadroom extends ChartWidget
{
    use AuthorisesWidgetView;
    use QueriesLedger;

    /**
     * Lower edges of each bucket, in percent of the context window.
     *
     * @var list<int>
     */
    public const array EDGES = [0, 25, 50, 75, 90, 100];

    /** Requests at or above this share are called out in the description. */
    public const int NEAR_LIMIT = 90;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '320px';

    protected int $nearLimit = 0;

    protected int $counted = 0;

    public function getHeading(): string
    {
        return __('filament-headroom::widgets.context_headroom.heading');
    }

    public function getDescription(): string
    {
        $this->getCachedData();

        if ($this->counted === 0) {
            return __('filament-headroom::widgets.context_headroom.empty');
        }

        return trans_choice('filament-headroom::widgets.context_headroom.near_limit', $this->nearLimit, [
            'count' => Format::number($this->nearLimit),
            'percent' => self::NEAR_LIMIT,
        ]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Counts per bucket from "at or above" counts at each edge: a bucket is its
     * edge's count minus the next edge's.
     *
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function getData(): array
    {
        $atOrAbove = array_map(fn (int $edge): int => $this->ledger()->nearLimit($edge)->count(), self::EDGES);

        $this->counted = $atOrAbove[0];
        $this->nearLimit = $atOrAbove[array_search(self::NEAR_LIMIT, self::EDGES, true)];

        if ($this->counted === 0) {
            return ['labels' => [], 'datasets' => []];
        }

        $counts = array_map(fn (int $count, int $above): int => $count - $above, $atOrAbove, [...array_slice($atOrAbove, 1), 0]);
        $labels = [];

        foreach (self::EDGES as $index => $edge) {
            $next = self::EDGES[$index + 1] ?? null;
            $labels[] = $next === null
                ? __('filament-headroom::widgets.context_headroom.over', ['percent' => $edge])
                : __('filament-headroom::widgets.context_headroom.bucket', ['from' => $edge, 'to' => $next]);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => __('filament-headroom::widgets.context_headroom.requests'), 'data' => $counts, 'slot' => 0],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        $locale = Js::from(str_replace('_', '-', app()->getLocale()));
        $colour = ChartPalette::scriptable();

        return RawJs::make(<<<JS
            {
                datasets: {
                    bar: {
                        backgroundColor: {$colour},
                        borderWidth: 0,
                        borderRadius: { topLeft: 4, topRight: 4 },
                        borderSkipped: 'start',
                        maxBarThickness: 48,
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + new Intl.NumberFormat({$locale}).format(context.parsed.y),
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            }
            JS);
    }
}
