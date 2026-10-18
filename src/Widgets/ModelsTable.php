<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Phoenix1331\AiHeadroomLedger\Data\AccuracyStats;
use Phoenix1331\AiHeadroomLedger\Data\ContextUsage;
use Phoenix1331\AiHeadroomLedger\Data\SpendSummary;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;
use Phoenix1331\FilamentHeadroom\Concerns\QueriesLedger;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Support\CalibrationStatus;
use Phoenix1331\FilamentHeadroom\Support\Cost;
use Phoenix1331\FilamentHeadroom\Support\Format;

/**
 * One row per model for the Models page. Spend and context group by the
 * model that answered (else the one counted); accuracy and calibration
 * belong to the model that was counted, so failover answers are left out
 * of them.
 */
class ModelsTable extends TableWidget
{
    use AuthorisesWidgetView;
    use QueriesLedger;

    /** Record key for requests with no model recorded. */
    public const string UNKNOWN_MODEL_KEY = '__unknown';

    protected int|string|array $columnSpan = 'full';

    /**
     * Part of the Models page rather than the widgets() list: the page's own
     * pages() entry enables it, so only authorisation applies here. Mount and
     * hydrate still enforce it through AuthorisesWidgetView.
     */
    public static function canView(): bool
    {
        return HeadroomPlugin::allows();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament-headroom::widgets.models_table.heading'))
            ->description(__('filament-headroom::widgets.models_table.description'))
            ->records(fn (?string $sortColumn, ?string $sortDirection): array => $this->rows($sortColumn ?? 'requests', $sortDirection ?? 'desc'))
            ->defaultSort('requests', 'desc')
            ->columns([
                TextColumn::make('model')
                    ->label(__('filament-headroom::widgets.models_table.columns.model'))
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('requests')
                    ->label(__('filament-headroom::widgets.models_table.columns.requests'))
                    ->formatStateUsing(fn (int $state): string => Format::number($state))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('spend')
                    ->label(__('filament-headroom::widgets.models_table.columns.spend'))
                    ->formatStateUsing(fn (Cost $state): Cost => $state)
                    ->html()
                    ->wrap(),
                TextColumn::make('average_context')
                    ->label(__('filament-headroom::widgets.models_table.columns.average_context'))
                    ->formatStateUsing(fn (float $state): string => Format::percent($state))
                    ->description(fn (array $record): ?string => self::peak($record))
                    ->placeholder('-')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('blocked')
                    ->label(__('filament-headroom::widgets.models_table.columns.blocked'))
                    ->sortable()
                    ->color(fn (int $state): ?string => $state > 0 ? 'danger' : null)
                    ->alignEnd(),
                TextColumn::make('warned')
                    ->label(__('filament-headroom::widgets.models_table.columns.warned'))
                    ->sortable()
                    ->color(fn (int $state): ?string => $state > 0 ? 'warning' : null)
                    ->alignEnd(),
                TextColumn::make('error')
                    ->label(__('filament-headroom::widgets.models_table.columns.error'))
                    ->formatStateUsing(fn (float $state): string => Format::percent($state))
                    ->tooltip(fn (array $record): ?string => is_int($record['samples'] ?? null) && $record['samples'] > 0
                        ? trans_choice('filament-headroom::widgets.models_table.samples', $record['samples'], ['count' => Format::number($record['samples'])])
                        : null)
                    ->description(fn (array $record): ?string => self::bias($record))
                    ->placeholder('-')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('calibration')
                    ->label(__('filament-headroom::widgets.models_table.columns.calibration'))
                    ->badge()
                    ->formatStateUsing(fn (CalibrationStatus $state): string => $state->label())
                    ->color(fn (CalibrationStatus $state): string => $state->color())
                    ->placeholder('-'),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('filament-headroom::widgets.models_table.empty'))
            ->emptyStateIcon('heroicon-o-cpu-chip');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rows(string $sortColumn, string $sortDirection): array
    {
        $spend = $this->ledger()->byModel()->spend();
        $context = $this->ledger()->byModel()->context();
        $accuracy = $this->ledger()->accuracy();

        $rows = [];

        foreach ($spend->keys() as $model) {
            $summary = $spend->get($model);
            $usage = $context->get($model);

            if (! $summary instanceof SpendSummary || ! $usage instanceof ContextUsage) {
                continue;
            }

            $rows[$model ?? self::UNKNOWN_MODEL_KEY] = self::row($model, $summary, $usage, $accuracy);
        }

        uasort($rows, function (array $a, array $b) use ($sortColumn, $sortDirection): int {
            $order = ($a[$sortColumn] ?? null) <=> ($b[$sortColumn] ?? null);

            return $sortDirection === 'asc' ? $order : -$order;
        });

        return $rows;
    }

    /**
     * @param  array<mixed>  $record
     */
    private static function peak(array $record): ?string
    {
        $peak = $record['peak_context'] ?? null;

        if (! is_float($peak)) {
            return null;
        }

        return __('filament-headroom::widgets.models_table.peak', ['percent' => Format::percent($peak)]);
    }

    /**
     * @param  array<mixed>  $record
     */
    private static function bias(array $record): ?string
    {
        $bias = $record['bias'] ?? null;

        if (! is_float($bias)) {
            return null;
        }

        return __('filament-headroom::widgets.models_table.bias', ['percent' => ($bias > 0 ? '+' : '').Format::percent($bias)]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(?string $model, SpendSummary $spend, ContextUsage $context, AccuracyStats $accuracy): array
    {
        $stat = $model === null ? null : $accuracy->for($model);

        return [
            'key' => $model ?? self::UNKNOWN_MODEL_KEY,
            'model' => $model ?? __('filament-headroom::widgets.models_table.unknown_model'),
            'requests' => $context->requests,
            'spend' => Cost::fromSummary($spend),
            'average_context' => $context->averagePercentUsed(),
            'peak_context' => $context->samples === 0 ? null : $context->maxPercentUsed,
            'blocked' => $context->blocked,
            'warned' => $context->warned,
            'error' => $stat?->errorPercent(),
            'bias' => $stat?->biasPercent(),
            'samples' => $stat->samples ?? 0,
            'calibration' => $model === null ? null : CalibrationStatus::for($model),
        ];
    }
}
