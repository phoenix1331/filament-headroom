<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Js;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroomLedger\Data\AccuracyStats;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;
use Phoenix1331\FilamentHeadroom\Concerns\QueriesLedger;
use Phoenix1331\FilamentHeadroom\Support\ChartPalette;

/**
 * How far Headroom's pre-flight estimates were from reported input tokens,
 * per model over time, optionally for one count method. Points are buckets
 * of Range::bucketDays() so the query count stays small; a bucket without
 * samples is a gap, not zero.
 */
class EstimateAccuracy extends ChartWidget
{
    use AuthorisesWidgetView;
    use QueriesLedger;

    public const string ALL_METHODS = 'all';

    public ?string $filter = self::ALL_METHODS;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected int $samples = 0;

    public function getHeading(): string
    {
        return __('filament-headroom::widgets.estimate_accuracy.heading');
    }

    public function getDescription(): string
    {
        $this->getCachedData();

        if ($this->samples === 0) {
            return __('filament-headroom::widgets.estimate_accuracy.empty');
        }

        return __('filament-headroom::widgets.estimate_accuracy.description');
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
        $filters = [self::ALL_METHODS => __('filament-headroom::widgets.estimate_accuracy.all_methods')];

        foreach (CountMethod::cases() as $method) {
            $filters[$method->value] = __("filament-headroom::widgets.estimate_accuracy.methods.{$method->value}");
        }

        return $filters;
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function getData(): array
    {
        $method = CountMethod::tryFrom($this->filter ?? '');
        $buckets = $this->range()->buckets();
        $labels = array_map(fn (array $bucket): string => $bucket[0]->translatedFormat('j M'), $buckets);

        $models = $this->topModels($this->ledger()->accuracy(), $method);

        if ($models === []) {
            return ['labels' => $labels, 'datasets' => []];
        }

        $perBucket = array_map(
            fn (array $bucket): AccuracyStats => $this->ledgerBase()->between(...$bucket)->accuracy(),
            $buckets,
        );
        $slots = ChartPalette::slotsFor($models);

        $datasets = [];

        foreach ($models as $model) {
            $datasets[] = [
                'label' => $model,
                'data' => array_map(fn (AccuracyStats $stats): ?float => $stats->for($model, $method)?->errorPercent(), $perBucket),
                'samples' => array_map(fn (AccuracyStats $stats): int => $stats->for($model, $method)->samples ?? 0, $perBucket),
                'slot' => $slots[$model],
            ];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }

    protected function getOptions(): RawJs
    {
        $locale = Js::from(str_replace('_', '-', app()->getLocale()));
        $colour = ChartPalette::scriptable();
        $samples = Js::from(__('filament-headroom::widgets.estimate_accuracy.samples'));

        return RawJs::make(<<<JS
            {
                datasets: {
                    line: {
                        borderColor: {$colour},
                        backgroundColor: {$colour},
                        pointBackgroundColor: {$colour},
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointHitRadius: 12,
                        spanGaps: false,
                    },
                },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': '
                                + new Intl.NumberFormat({$locale}, { maximumFractionDigits: 1 }).format(context.parsed.y) + '%'
                                + ' (' + new Intl.NumberFormat({$locale}).format(context.dataset.samples[context.dataIndex]) + ' ' + {$samples} + ')',
                        },
                    },
                },
                scales: {
                    x: { ticks: { maxRotation: 0, autoSkipPadding: 16 } },
                    y: { beginAtZero: true, ticks: { callback: (value) => value + '%' } },
                },
            }
            JS);
    }

    /**
     * Models with samples in the range, most samples first, up to the palette size.
     *
     * @return list<string>
     */
    private function topModels(AccuracyStats $stats, ?CountMethod $method): array
    {
        $samples = [];

        foreach ($stats as $stat) {
            if ($stat->model === null || ($method !== null && $stat->countMethod !== $method)) {
                continue;
            }

            $samples[$stat->model] = ($samples[$stat->model] ?? 0) + $stat->samples;
        }

        $samples = array_filter($samples);
        $this->samples = array_sum($samples);
        arsort($samples);

        return array_slice(array_map('strval', array_keys($samples)), 0, ChartPalette::slots());
    }
}
