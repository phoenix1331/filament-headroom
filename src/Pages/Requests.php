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
        return __('filament-headroom::pages.requests.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int|string $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => $this->page(
                (int) $page,
                (int) $recordsPerPage,
                $sortColumn,
                $sortDirection,
            ))
            ->columns($this->columns())
            ->defaultSort('counted_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('filament-headroom::pages.requests.empty'))
            ->emptyStateIcon('heroicon-o-queue-list');
    }

    /**
     * The query every row comes from, before sorting and paging.
     */
    protected function query(): LedgerQuery
    {
        return Range::resolve(null)->apply(HeadroomPlugin::ledger());
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
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    protected function page(int $page, int $perPage, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator
    {
        $query = $this->query()->orderBy(self::SORTS[$sortColumn ?? ''] ?? EntrySort::CountedAt, $sortDirection !== 'asc');
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
