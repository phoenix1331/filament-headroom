<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Phoenix1331\AiHeadroomLedger\Data\LedgerEntry;
use Phoenix1331\AiHeadroomLedger\Enums\Outcome;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;
use Phoenix1331\FilamentHeadroom\Concerns\QueriesLedger;
use Phoenix1331\FilamentHeadroom\Support\Format;
use Phoenix1331\FilamentHeadroom\Support\Outcomes;

/**
 * The latest requests in the range that were blocked, warned or failed, with
 * the reason the ledger recorded.
 */
class BlockedWarnedFailed extends TableWidget
{
    use AuthorisesWidgetView;
    use QueriesLedger;

    public const int LIMIT = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament-headroom::widgets.blocked_warned_failed.heading'))
            ->description(__('filament-headroom::widgets.blocked_warned_failed.description', ['count' => self::LIMIT]))
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('counted_at')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.when'))
                    ->since()
                    ->dateTimeTooltip(),
                TextColumn::make('outcome')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.outcome'))
                    ->badge()
                    ->formatStateUsing(fn (Outcome $state): string => Outcomes::label($state))
                    ->color(fn (Outcome $state): string => Outcomes::color($state)),
                TextColumn::make('reason')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.reason'))
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('agent')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.agent'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->tooltip(fn (array $record): ?string => is_string($record['agent'] ?? null) ? $record['agent'] : null)
                    ->placeholder('-'),
                TextColumn::make('model')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.model'))
                    ->placeholder('-'),
                TextColumn::make('percent_used')
                    ->label(__('filament-headroom::widgets.blocked_warned_failed.columns.context'))
                    ->formatStateUsing(fn (float $state): string => Format::percent($state))
                    ->placeholder('-')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('filament-headroom::widgets.blocked_warned_failed.empty'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rows(): array
    {
        $rows = [];

        foreach ($this->ledger()->withOutcome(Outcome::Blocked, Outcome::Warned, Outcome::Failed)->entries(self::LIMIT) as $entry) {
            $rows[$entry->requestId] = self::row($entry);
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
            'counted_at' => $entry->countedAt ?? $entry->usedAt,
            'outcome' => $entry->outcome,
            'reason' => Outcomes::reason($entry->outcomeReason),
            'agent' => $entry->agent,
            'model' => $entry->model(),
            'percent_used' => $entry->percentUsed,
        ];
    }
}
