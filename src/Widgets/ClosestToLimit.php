<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Phoenix1331\AiHeadroomLedger\Data\LedgerEntry;
use Phoenix1331\AiHeadroomLedger\Enums\EntrySort;
use Phoenix1331\AiHeadroomLedger\Enums\Outcome;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;
use Phoenix1331\FilamentHeadroom\Concerns\QueriesLedger;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\RequestDetail;
use Phoenix1331\FilamentHeadroom\Pages\Requests;
use Phoenix1331\FilamentHeadroom\Support\Format;
use Phoenix1331\FilamentHeadroom\Support\Outcomes;

/**
 * The requests in the range that came closest to their context window and
 * were still sent. Blocked requests are left out: they are listed in the
 * blocked, warned and failed table.
 */
class ClosestToLimit extends TableWidget
{
    use AuthorisesWidgetView;
    use QueriesLedger;

    public const int LIMIT = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament-headroom::widgets.closest_to_limit.heading'))
            ->description(__('filament-headroom::widgets.closest_to_limit.description', ['count' => self::LIMIT]))
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('percent_used')
                    ->label(__('filament-headroom::widgets.closest_to_limit.columns.context'))
                    ->formatStateUsing(fn (float $state): string => Format::percent($state))
                    ->color(fn (float $state): ?string => $state >= 80 ? 'warning' : null)
                    ->weight('medium'),
                TextColumn::make('tokens')
                    ->label(__('filament-headroom::widgets.closest_to_limit.columns.tokens'))
                    ->placeholder('-'),
                TextColumn::make('outcome')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.outcome'))
                    ->badge()
                    ->formatStateUsing(fn (Outcome $state): string => Outcomes::label($state))
                    ->color(fn (Outcome $state): string => Outcomes::color($state)),
                TextColumn::make('agent')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.agent'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->placeholder('-'),
                TextColumn::make('model')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.model'))
                    ->placeholder('-'),
                TextColumn::make('counted_at')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.when'))
                    ->since()
                    ->dateTimeTooltip(),
            ])
            ->recordUrl(fn (array $record): ?string => self::detailUrl($record))
            ->paginated(false)
            ->emptyStateHeading(__('filament-headroom::widgets.closest_to_limit.empty'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rows(): array
    {
        $entries = $this->ledger()
            ->withOutcome(Outcome::Counted, Outcome::Completed, Outcome::Warned, Outcome::Failed)
            ->orderBy(EntrySort::PercentUsed)
            ->entries(self::LIMIT);

        $rows = [];

        foreach ($entries as $entry) {
            if ($entry->percentUsed !== null) {
                $rows[$entry->requestId] = self::row($entry);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(LedgerEntry $entry): array
    {
        return [
            'key' => $entry->requestId,
            'percent_used' => $entry->percentUsed,
            'tokens' => $entry->estimatedInputTokens === null || $entry->contextWindow === null
                ? null
                : __('filament-headroom::widgets.closest_to_limit.of_window', [
                    'tokens' => Format::number($entry->estimatedInputTokens),
                    'window' => Format::number($entry->contextWindow),
                ]),
            'outcome' => $entry->outcome,
            'agent' => $entry->agent,
            'model' => $entry->model(),
            'counted_at' => $entry->countedAt ?? $entry->usedAt,
        ];
    }

    /**
     * The detail page when this panel registers it (the Requests page is enabled).
     *
     * @param  array<mixed>  $record
     */
    private static function detailUrl(array $record): ?string
    {
        $pages = HeadroomPlugin::current()?->getPages() ?? [];
        $id = $record['key'] ?? null;

        if (! is_string($id) || array_filter($pages, fn (string $page): bool => is_a($page, Requests::class, true)) === []) {
            return null;
        }

        return RequestDetail::getUrl(['requestId' => $id]);
    }
}
