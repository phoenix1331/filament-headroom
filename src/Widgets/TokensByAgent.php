<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Js;
use Phoenix1331\AiHeadroomLedger\Data\Group;
use Phoenix1331\AiHeadroomLedger\Data\TokenTotals;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;
use Phoenix1331\FilamentHeadroom\Concerns\QueriesLedger;
use Phoenix1331\FilamentHeadroom\Support\ChartPalette;
use Phoenix1331\FilamentHeadroom\Support\Format;

/**
 * Reported input and output tokens per agent as stacked horizontal bars.
 * Requests that never reported usage have no actual tokens; they are counted
 * in the description, never drawn as if they were usage.
 */
class TokensByAgent extends ChartWidget
{
    use AuthorisesWidgetView;
    use QueriesLedger;

    /** Agents drawn as their own bar; the rest share one. */
    public const int MAX_AGENTS = 10;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '360px';

    protected int $unreported = 0;

    public function getHeading(): string
    {
        return __('filament-headroom::widgets.tokens_by_agent.heading');
    }

    public function getDescription(): ?string
    {
        $this->getCachedData();

        if ($this->unreported === 0) {
            return null;
        }

        return trans_choice('filament-headroom::widgets.tokens_by_agent.unreported', $this->unreported, ['count' => Format::number($this->unreported)]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function getData(): array
    {
        $groups = $this->ledger()->byAgent()->tokens()->all();

        $this->unreported = array_sum(array_map(
            fn (Group $group): int => $group->value->requests - $group->value->requestsWithUsage,
            $groups,
        ));

        $groups = array_values(array_filter($groups, fn (Group $group): bool => $group->value->input + $group->value->output > 0));
        usort($groups, fn (Group $a, Group $b): int => ($b->value->input + $b->value->output) <=> ($a->value->input + $a->value->output));

        $shown = array_slice($groups, 0, self::MAX_AGENTS);
        $rest = array_slice($groups, self::MAX_AGENTS);

        $labels = self::labels($shown);
        $input = array_map(fn (Group $group): int => $group->value->input, $shown);
        $output = array_map(fn (Group $group): int => $group->value->output, $shown);

        if ($rest !== []) {
            $other = array_reduce($rest, fn (TokenTotals $total, Group $group): TokenTotals => $total->plus($group->value), new TokenTotals);
            $labels[] = __('filament-headroom::widgets.tokens_by_agent.other');
            $input[] = $other->input;
            $output[] = $other->output;
        }

        if ($labels === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => __('filament-headroom::widgets.tokens_by_agent.input'), 'data' => $input, 'slot' => 0],
                ['label' => __('filament-headroom::widgets.tokens_by_agent.output'), 'data' => $output, 'slot' => 1],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        $locale = Js::from(str_replace('_', '-', app()->getLocale()));
        $colour = ChartPalette::scriptable();
        $surface = ChartPalette::surface();

        return RawJs::make(<<<JS
            {
                indexAxis: 'y',
                datasets: {
                    bar: {
                        backgroundColor: {$colour},
                        borderColor: {$surface},
                        borderWidth: { right: 2 },
                        borderRadius: (context) => context.datasetIndex === context.chart.data.datasets.length - 1 ? { topRight: 4, bottomRight: 4 } : 0,
                        borderSkipped: false,
                        maxBarThickness: 18,
                    },
                },
                interaction: { mode: 'index', axis: 'y', intersect: false },
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + new Intl.NumberFormat({$locale}).format(context.parsed.x),
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { callback: (value) => new Intl.NumberFormat({$locale}, { notation: 'compact' }).format(value) },
                    },
                    y: { stacked: true, grid: { display: false } },
                },
            }
            JS);
    }

    /**
     * Agent class basenames, or the full name where two agents share one.
     *
     * @param  list<Group<TokenTotals>>  $groups
     * @return list<string>
     */
    private static function labels(array $groups): array
    {
        $basenames = array_map(
            fn (Group $group): ?string => $group->key === null ? null : class_basename($group->key),
            $groups,
        );
        $counts = array_count_values(array_filter($basenames, is_string(...)));

        return array_map(
            fn (Group $group, ?string $basename): string => match (true) {
                $group->key === null || $basename === null => __('filament-headroom::widgets.tokens_by_agent.no_agent'),
                $counts[$basename] > 1 => $group->key,
                default => $basename,
            },
            $groups,
            $basenames,
        );
    }
}
