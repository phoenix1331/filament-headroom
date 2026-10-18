<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Pages\Concerns;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Phoenix1331\AiHeadroomLedger\Data\ContextUsage;
use Phoenix1331\AiHeadroomLedger\Data\SpendSummary;
use Phoenix1331\AiHeadroomLedger\Data\TokenTotals;
use Phoenix1331\AiHeadroomLedger\Enums\EntrySort;
use Phoenix1331\AiHeadroomLedger\Query\LedgerQuery;
use Phoenix1331\FilamentHeadroom\Support\Cost;
use Phoenix1331\FilamentHeadroom\Support\Format;

/**
 * The Requests page's run view (?group=run): one row per agent run, from
 * the same filters, with totals from byRun(). The ledger cannot page runs,
 * so they are ordered and paged here: by most recent activity unless a
 * column sort is chosen, and only rows on the current page look up their run
 * summary (agent, start, end).
 */
trait GroupsByRun
{
    public const string GROUP_BY_RUN = 'run';

    /**
     * Run columns sorted here rather than by the ledger.
     *
     * @var list<string>
     */
    public const array RUN_SORTS = ['steps', 'input_tokens', 'output_tokens', 'peak_context', 'cost'];

    /** Entries read per batch when ordering runs by recent activity. */
    public const int RUN_SCAN_BATCH = 200;

    #[Url(as: 'group')]
    public ?string $grouping = null;

    public function isGroupedByRun(): bool
    {
        return $this->grouping === self::GROUP_BY_RUN;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if ($this->isGroupedByRun()) {
            return [
                Action::make('ungroup')
                    ->label(__('filament-headroom::pages.requests.runs.show_requests'))
                    ->icon(Heroicon::OutlinedQueueList)
                    ->color('gray')
                    ->url(static::getUrl()),
            ];
        }

        return [
            Action::make('groupByRun')
                ->label(__('filament-headroom::pages.requests.runs.group_by_run'))
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('gray')
                ->url(static::getUrl(['group' => self::GROUP_BY_RUN])),
        ];
    }

    /**
     * @return array<TextColumn>
     */
    protected function runColumns(): array
    {
        return [
            TextColumn::make('started_at')
                ->label(__('filament-headroom::pages.requests.runs.columns.started'))
                ->dateTime('j M, H:i')
                ->description(fn (array $record): ?string => self::duration($record))
                ->placeholder('-'),
            TextColumn::make('agent')
                ->label(__('filament-headroom::pages.requests.columns.agent'))
                ->formatStateUsing(fn (string $state): string => class_basename($state))
                // The tail: UUIDv7s share their leading timestamp digits.
                ->description(fn (array $record): string => is_string($record['key'] ?? null) ? mb_substr($record['key'], -8) : '')
                ->placeholder('-'),
            TextColumn::make('steps')
                ->label(__('filament-headroom::pages.requests.runs.columns.steps'))
                ->description(fn (array $record): ?string => self::problems($record))
                ->sortable()
                ->alignEnd(),
            TextColumn::make('input_tokens')
                ->label(__('filament-headroom::pages.requests.columns.input_tokens'))
                ->formatStateUsing(fn (int $state): string => Format::number($state))
                ->placeholder('-')
                ->sortable()
                ->alignEnd(),
            TextColumn::make('output_tokens')
                ->label(__('filament-headroom::pages.requests.columns.output_tokens'))
                ->formatStateUsing(fn (int $state): string => Format::number($state))
                ->placeholder('-')
                ->sortable()
                ->alignEnd(),
            TextColumn::make('peak_context')
                ->label(__('filament-headroom::pages.requests.runs.columns.peak_context'))
                ->formatStateUsing(fn (float $state): string => Format::percent($state))
                ->color(fn (float $state): ?string => $state >= 100 ? 'danger' : ($state >= 80 ? 'warning' : null))
                ->placeholder('-')
                ->sortable()
                ->alignEnd(),
            TextColumn::make('cost')
                ->label(__('filament-headroom::pages.requests.columns.cost'))
                ->formatStateUsing(fn (Cost $state): Cost => $state)
                ->html()
                ->wrap()
                ->sortable(),
        ];
    }

    /**
     * One page of runs, newest first unless a column sort is chosen.
     *
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    protected function runPage(LedgerQuery $query, int $page, int $perPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator
    {
        $grouped = $query->byRun();
        $spend = $grouped->spend();
        $tokens = $grouped->tokens();
        $context = $grouped->context();

        $runs = [];

        foreach ($spend as $group) {
            $tokenTotals = $tokens->get($group->key);
            $usage = $context->get($group->key);

            if ($group->key === null || ! $tokenTotals instanceof TokenTotals || ! $usage instanceof ContextUsage) {
                continue;
            }

            $runs[$group->key] = self::runTotals($group->key, $group->value, $tokenTotals, $usage);
        }

        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $runs = $sortColumn !== null && in_array($sortColumn, self::RUN_SORTS, true)
            ? self::sortRuns($runs, $sortColumn, $sortDirection)
            : self::byRecentActivity($runs, $this->recentRunIds($query, $page * $perPage));
        $rows = $this->withRunSummaries($query, array_slice($runs, ($page - 1) * $perPage, $perPage, true));

        return new LengthAwarePaginator($rows, count($runs), $perPage, $page);
    }

    /**
     * Agent and timing from each run's summary, through the same filters, so
     * a tenant-scoped query never reads another tenant's steps.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function withRunSummaries(LedgerQuery $query, array $rows): array
    {
        $result = [];

        foreach ($rows as $id => $row) {
            $summary = $query->run($id);
            $row['agent'] = $summary?->agent;
            $row['started_at'] = $summary?->startedAt;
            $row['ended_at'] = $summary?->endedAt;
            $row['detail_id'] = $summary?->steps->first()?->requestId;
            $result[$id] = $row;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function runTotals(string $id, SpendSummary $spend, TokenTotals $tokens, ContextUsage $context): array
    {
        return [
            'key' => $id,
            'steps' => $tokens->requests,
            'problems' => $context->warned + $context->blocked + $context->failed,
            'input_tokens' => $tokens->requestsWithUsage === 0 ? null : $tokens->input,
            'output_tokens' => $tokens->requestsWithUsage === 0 ? null : $tokens->output,
            'peak_context' => $context->samples === 0 ? null : $context->maxPercentUsed,
            'cost' => Cost::fromSummary($spend),
            'cost_sort' => array_sum(array_map(fn ($money): int => $money->microUnits, $spend->total->all())),
        ];
    }

    /**
     * Run IDs in order of their newest matching step, read from the ledger's
     * time-sorted entries a batch at a time until $needed runs are found, so
     * early pages stay cheap. Invocation IDs are not assumed to sort by time.
     *
     * @return list<string>
     */
    private function recentRunIds(LedgerQuery $query, int $needed): array
    {
        $ids = [];
        $sorted = $query->orderBy(EntrySort::CountedAt);
        $batch = 1;

        do {
            $page = $sorted->paginate(self::RUN_SCAN_BATCH, $batch++);

            foreach ($page->entries as $entry) {
                if ($entry->invocationId !== null) {
                    $ids[$entry->invocationId] = true;
                }
            }
        } while (count($ids) < $needed && $page->hasMorePages());

        return array_map('strval', array_keys($ids));
    }

    /**
     * @param  array<string, array<string, mixed>>  $runs
     * @param  list<string>  $recent
     * @return array<string, array<string, mixed>>
     */
    private static function byRecentActivity(array $runs, array $recent): array
    {
        $ordered = [];

        foreach ($recent as $id) {
            if (isset($runs[$id])) {
                $ordered[$id] = $runs[$id];
            }
        }

        return $ordered + $runs;
    }

    /**
     * @param  array<string, array<string, mixed>>  $runs
     * @return array<string, array<string, mixed>>
     */
    private static function sortRuns(array $runs, string $sortColumn, ?string $sortDirection): array
    {
        $field = $sortColumn === 'cost' ? 'cost_sort' : $sortColumn;

        uasort($runs, function (array $a, array $b) use ($field, $sortDirection): int {
            $order = ($a[$field] ?? null) <=> ($b[$field] ?? null);

            return $sortDirection === 'asc' ? $order : -$order;
        });

        return $runs;
    }

    /**
     * @param  array<mixed>  $record
     */
    private static function duration(array $record): ?string
    {
        $start = $record['started_at'] ?? null;
        $end = $record['ended_at'] ?? null;

        if (! $start instanceof CarbonImmutable || ! $end instanceof CarbonImmutable || ($record['steps'] ?? 0) === 1) {
            return null;
        }

        return $start->diffForHumans($end, syntax: CarbonImmutable::DIFF_ABSOLUTE, short: true);
    }

    /**
     * @param  array<mixed>  $record
     */
    private static function problems(array $record): ?string
    {
        $problems = $record['problems'] ?? 0;

        if (! is_int($problems) || $problems === 0) {
            return null;
        }

        return trans_choice('filament-headroom::pages.requests.runs.problems', $problems, ['count' => $problems]);
    }
}
