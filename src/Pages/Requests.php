<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroomLedger\Data\LedgerEntry;
use Phoenix1331\AiHeadroomLedger\Enums\EntrySort;
use Phoenix1331\AiHeadroomLedger\Enums\Outcome;
use Phoenix1331\AiHeadroomLedger\Query\LedgerQuery;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesPageAccess;
use Phoenix1331\FilamentHeadroom\Concerns\HasHeadroomNavigation;
use Phoenix1331\FilamentHeadroom\Enums\Range;
use Phoenix1331\FilamentHeadroom\Enums\ValueBasis;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Concerns\GroupsByRun;
use Phoenix1331\FilamentHeadroom\Support\Cost;
use Phoenix1331\FilamentHeadroom\Support\Format;
use Phoenix1331\FilamentHeadroom\Support\Outcomes;

/**
 * Every ledger entry as a read-only table, paginated and sorted by the
 * ledger, newest first.
 */
class Requests extends Page implements HasTable
{
    use AuthorisesPageAccess;
    use GroupsByRun;
    use HasHeadroomNavigation;
    use InteractsWithTable;

    /**
     * Table columns the ledger can sort by.
     *
     * @var array<string, EntrySort>
     */
    public const array SORTS = [
        'counted_at' => EntrySort::CountedAt,
        'input_tokens' => EntrySort::InputTokens,
        'output_tokens' => EntrySort::OutputTokens,
        'percent_used' => EntrySort::PercentUsed,
        'cost' => EntrySort::Cost,
    ];

    protected static ?string $slug = 'headroom/requests';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function getNavigationLabel(): string
    {
        return __('filament-headroom::pages.requests.navigation_label');
    }

    public function getTitle(): string
    {
        if ($this->isGroupedByRun()) {
            return __('filament-headroom::pages.requests.runs.title');
        }

        return __('filament-headroom::pages.requests.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection, ?array $filters): LengthAwarePaginator => $this->page(
                (int) $page,
                (int) $recordsPerPage,
                $sortColumn,
                $sortDirection,
                $filters ?? [],
            ))
            ->columns($this->isGroupedByRun() ? $this->runColumns() : $this->columns())
            ->filters($this->filters(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->defaultSort($this->isGroupedByRun() ? null : 'counted_at', 'desc')
            ->recordUrl(fn (array $record): ?string => self::detailUrl($record))
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading($this->isGroupedByRun() ? __('filament-headroom::pages.requests.runs.empty') : __('filament-headroom::pages.requests.empty'))
            ->emptyStateIcon('heroicon-o-queue-list');
    }

    /**
     * The ledger query for the table's filter state, before sorting and paging.
     *
     * @param  array<mixed>  $filters
     */
    protected function query(array $filters): LedgerQuery
    {
        $query = Range::resolve(self::value($filters, 'range'))->apply(HeadroomPlugin::ledger());

        $agent = self::value($filters, 'agent');
        $provider = self::value($filters, 'provider');
        $model = self::value($filters, 'model');
        $outcomes = array_filter(array_map(Outcome::tryFrom(...), self::values($filters, 'outcome')));
        $methods = array_filter(array_map(CountMethod::tryFrom(...), self::values($filters, 'count_method')));

        $query = $agent === null ? $query : $query->forAgent($agent);
        $query = $provider === null ? $query : $query->forProvider($provider);
        $query = $model === null ? $query : $query->forModel($model);
        $query = $outcomes === [] ? $query : $query->withOutcome(...$outcomes);
        $query = $methods === [] ? $query : $query->forCountMethod(...$methods);

        foreach (self::tagKeys() as $key) {
            $value = self::value($filters, self::tagFilter($key));
            $query = $value === null ? $query : $query->forTag($key, $value);
        }

        return $query;
    }

    /**
     * @return array<SelectFilter>
     */
    protected function filters(): array
    {
        $filters = [
            SelectFilter::make('range')
                ->label(__('filament-headroom::ranges.label'))
                ->options(fn (): array => Range::options())
                ->default(fn (): string => Range::resolve(null)->value)
                ->selectablePlaceholder(false),
            SelectFilter::make('agent')
                ->label(__('filament-headroom::pages.requests.columns.agent'))
                ->options(fn (): array => $this->options(fn (LedgerQuery $query): array => $query->byAgent()->tokens()->keys(), class_basename(...)))
                ->searchable(),
            SelectFilter::make('provider')
                ->label(__('filament-headroom::pages.requests.filters.provider'))
                ->options(fn (): array => $this->options(fn (LedgerQuery $query): array => $query->byProvider()->tokens()->keys())),
            SelectFilter::make('model')
                ->label(__('filament-headroom::pages.requests.columns.model'))
                ->options(fn (): array => $this->options(fn (LedgerQuery $query): array => $query->byModel()->tokens()->keys()))
                ->searchable(),
            SelectFilter::make('outcome')
                ->label(__('filament-headroom::pages.requests.columns.outcome'))
                ->options(array_combine(
                    array_map(fn (Outcome $outcome): string => $outcome->value, Outcome::cases()),
                    array_map(Outcomes::label(...), Outcome::cases()),
                ))
                ->multiple(),
            SelectFilter::make('count_method')
                ->label(__('filament-headroom::pages.requests.filters.count_method'))
                ->options(array_combine(
                    array_map(fn (CountMethod $method): string => $method->value, CountMethod::cases()),
                    array_map(fn (CountMethod $method): string => __("filament-headroom::count_methods.{$method->value}"), CountMethod::cases()),
                ))
                ->multiple(),
        ];

        foreach (self::tagKeys() as $key) {
            $filters[] = SelectFilter::make(self::tagFilter($key))
                ->label($key)
                ->options(fn (): array => $this->options(
                    fn (LedgerQuery $query): array => $query->byTag($key)->tokens()->keys(),
                    fn (string $value): string => HeadroomPlugin::current()?->getTagLabel($key, $value) ?? $value,
                ))
                ->searchable();
        }

        return $filters;
    }

    /**
     * Filter choices from the widest range, so they do not shift as the range
     * changes. Requests without a value (the null group) are not offered.
     *
     * @param  callable(LedgerQuery): list<string|null>  $keys
     * @param  (callable(string): string)|null  $label
     * @return array<string, string>
     */
    protected function options(callable $keys, ?callable $label = null): array
    {
        $options = [];

        foreach ($keys(Range::NinetyDays->apply(HeadroomPlugin::ledger())) as $key) {
            if ($key !== null) {
                $options[$key] = $label === null ? $key : $label($key);
            }
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * Context tag keys the ledger records, from its allowlist.
     *
     * @return list<string>
     */
    protected static function tagKeys(): array
    {
        $keys = config('headroom-ledger.tags.allowed', []);

        return is_array($keys) ? array_values(array_filter($keys, is_string(...))) : [];
    }

    protected static function tagFilter(string $key): string
    {
        return 'tag_'.$key;
    }

    /**
     * @param  array<mixed>  $filters
     */
    private static function value(array $filters, string $name): ?string
    {
        $state = $filters[$name] ?? null;
        $value = is_array($state) ? ($state['value'] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<mixed>  $filters
     * @return list<string>
     */
    private static function values(array $filters, string $name): array
    {
        $state = $filters[$name] ?? null;
        $values = is_array($state) ? ($state['values'] ?? null) : null;

        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }

    /**
     * @return array<TextColumn>
     */
    protected function columns(): array
    {
        return [
            TextColumn::make('counted_at')
                ->label(__('filament-headroom::pages.requests.columns.when'))
                ->dateTime('j M, H:i')
                ->description(fn (array $record): ?string => self::since($record))
                ->sortable(),
            TextColumn::make('outcome')
                ->label(__('filament-headroom::pages.requests.columns.outcome'))
                ->badge()
                ->formatStateUsing(fn (Outcome $state): string => Outcomes::label($state))
                ->color(fn (Outcome $state): string => Outcomes::color($state)),
            TextColumn::make('agent')
                ->label(__('filament-headroom::pages.requests.columns.agent'))
                ->formatStateUsing(fn (string $state): string => class_basename($state))
                ->placeholder('-'),
            TextColumn::make('model')
                ->label(__('filament-headroom::pages.requests.columns.model'))
                ->description(fn (array $record): ?string => self::modelDetail($record))
                ->placeholder('-'),
            TextColumn::make('input_tokens')
                ->label(__('filament-headroom::pages.requests.columns.input_tokens'))
                ->formatStateUsing(fn (int $state): string => Format::number($state))
                ->description(fn (array $record): ?string => self::inputBasis($record))
                ->placeholder('-')
                ->sortable()
                ->alignEnd(),
            TextColumn::make('output_tokens')
                ->label(__('filament-headroom::pages.requests.columns.output_tokens'))
                ->formatStateUsing(fn (int $state): string => Format::number($state))
                ->placeholder('-')
                ->sortable()
                ->alignEnd(),
            TextColumn::make('percent_used')
                ->label(__('filament-headroom::pages.requests.columns.context'))
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
     * One page from the ledger as a paginator Filament can render.
     *
     * @param  array<mixed>  $filters
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    protected function page(int $page, int $perPage, ?string $sortColumn, ?string $sortDirection, array $filters = []): LengthAwarePaginator
    {
        if ($this->isGroupedByRun()) {
            return $this->runPage($this->query($filters), $page, $perPage, $sortColumn, $sortDirection);
        }

        $query = $this->query($filters)->orderBy(self::SORTS[$sortColumn ?? ''] ?? EntrySort::CountedAt, $sortDirection !== 'asc');
        $result = $query->paginate(max(1, $perPage), max(1, $page));

        $rows = [];

        foreach ($result->entries as $entry) {
            $rows[$entry->requestId] = self::row($entry);
        }

        return new LengthAwarePaginator($rows, $result->total, $result->perPage, $result->page);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function row(LedgerEntry $entry): array
    {
        return [
            'key' => $entry->requestId,
            'counted_at' => $entry->countedAt ?? $entry->usedAt,
            'outcome' => $entry->outcome,
            'agent' => $entry->agent,
            'model' => $entry->model(),
            'provider' => $entry->provider(),
            'count_method' => $entry->countMethod,
            'input_tokens' => $entry->actualInputTokens ?? $entry->estimatedInputTokens,
            'input_estimated' => $entry->actualInputTokens === null && $entry->estimatedInputTokens !== null,
            'output_tokens' => $entry->outputTokens,
            'percent_used' => $entry->percentUsed,
            'cost' => Cost::fromMoney($entry->cost()),
            'invocation_id' => $entry->invocationId,
            'tags' => $entry->tags,
        ];
    }

    /**
     * The request's detail page; a run row opens its first step.
     *
     * @param  array<mixed>  $record
     */
    private static function detailUrl(array $record): ?string
    {
        $id = $record['detail_id'] ?? $record['key'] ?? null;

        return is_string($id) ? RequestDetail::getUrl(['requestId' => $id]) : null;
    }

    /**
     * Provider and count method under the model, keeping the table narrow.
     *
     * @param  array<mixed>  $record
     */
    private static function modelDetail(array $record): ?string
    {
        $parts = [];

        if (is_string($record['provider'] ?? null)) {
            $parts[] = $record['provider'];
        }

        if (($record['count_method'] ?? null) instanceof CountMethod) {
            $parts[] = __('filament-headroom::pages.requests.counted', ['method' => mb_strtolower(__("filament-headroom::count_methods.{$record['count_method']->value}"))]);
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Input tokens fall back to the estimate when no usage was reported; say so.
     *
     * @param  array<mixed>  $record
     */
    private static function inputBasis(array $record): ?string
    {
        if (($record['input_estimated'] ?? false) !== true) {
            return null;
        }

        return ValueBasis::Estimated->getLabel();
    }

    /**
     * @param  array<mixed>  $record
     */
    private static function since(array $record): ?string
    {
        $at = $record['counted_at'] ?? null;

        return $at instanceof \DateTimeInterface ? CarbonImmutable::instance($at)->diffForHumans() : null;
    }
}
