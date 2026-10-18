<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Pages;

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Phoenix1331\AiHeadroomLedger\Data\LedgerEntry;
use Phoenix1331\AiHeadroomLedger\Data\RunSummary;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesPageAccess;
use Phoenix1331\FilamentHeadroom\Enums\ValueBasis;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Support\Cost;
use Phoenix1331\FilamentHeadroom\Support\Format;
use Phoenix1331\FilamentHeadroom\Support\Outcomes;

/**
 * One ledger entry: outcome, models, estimate against actual tokens, cost
 * with its basis, and for an agent run every step with its context growth.
 * Reached from the Requests page; registered whenever Requests is.
 */
class RequestDetail extends Page
{
    use AuthorisesPageAccess;

    protected static ?string $slug = 'headroom/requests/view';

    protected static bool $shouldRegisterNavigation = false;

    public string $requestId;

    protected ?LedgerEntry $entry = null;

    protected ?RunSummary $run = null;

    protected bool $loaded = false;

    public static function getRoutePath(Panel $panel): string
    {
        return '/headroom/requests/{requestId}';
    }

    public function mount(string $requestId): void
    {
        $this->requestId = $requestId;
        $this->entry();
    }

    /**
     * The entry, read once per request through the plugin's ledger query, so
     * an entry outside the user's scope is a 404 like a missing one.
     */
    public function entry(): LedgerEntry
    {
        if (! $this->loaded) {
            $this->entry = HeadroomPlugin::ledger()->find($this->requestId);
            $this->run = $this->entry?->invocationId === null ? null : HeadroomPlugin::ledger()->run($this->entry->invocationId);
            $this->loaded = true;
        }

        abort_if($this->entry === null, 404);

        return $this->entry;
    }

    public function run(): ?RunSummary
    {
        $this->entry();

        return $this->run;
    }

    public function getTitle(): string
    {
        return __('filament-headroom::pages.detail.title', ['id' => mb_substr($this->requestId, -8)]);
    }

    public function getSubheading(): string
    {
        $entry = $this->entry();

        return implode(' · ', array_filter([
            Outcomes::label($entry->outcome),
            Outcomes::reason($entry->outcomeReason),
            $entry->requestId,
        ]));
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('filament-headroom::pages.detail.back'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(Requests::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $entry = $this->entry();
        $run = $this->run();

        return $schema->components(array_values(array_filter([
            Section::make(__('filament-headroom::pages.detail.sections.request'))
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('outcome')
                        ->label(__('filament-headroom::pages.requests.columns.outcome'))
                        ->state(Outcomes::label($entry->outcome))
                        ->badge()
                        ->color(Outcomes::color($entry->outcome)),
                    TextEntry::make('reason')
                        ->label(__('filament-headroom::pages.detail.reason'))
                        ->state(Outcomes::reason($entry->outcomeReason))
                        ->placeholder('-'),
                    TextEntry::make('agent')
                        ->label(__('filament-headroom::pages.requests.columns.agent'))
                        ->state($entry->agent)
                        ->placeholder('-'),
                    TextEntry::make('model')
                        ->label(__('filament-headroom::pages.requests.columns.model'))
                        ->state(self::models($entry))
                        ->placeholder('-'),
                    TextEntry::make('count_method')
                        ->label(__('filament-headroom::pages.requests.filters.count_method'))
                        ->state($entry->countMethod === null ? null : __("filament-headroom::count_methods.{$entry->countMethod->value}"))
                        ->placeholder('-'),
                    TextEntry::make('streamed')
                        ->label(__('filament-headroom::pages.detail.streamed'))
                        ->state($entry->streamed ? __('filament-headroom::pages.detail.yes') : __('filament-headroom::pages.detail.no')),
                    TextEntry::make('counted_at')
                        ->label(__('filament-headroom::pages.detail.counted_at'))
                        ->state($entry->countedAt)
                        ->dateTime()
                        ->placeholder('-'),
                    TextEntry::make('used_at')
                        ->label(__('filament-headroom::pages.detail.used_at'))
                        ->state($entry->usedAt)
                        ->dateTime()
                        ->placeholder(__('filament-headroom::pages.detail.no_usage')),
                    TextEntry::make('tags')
                        ->label(__('filament-headroom::pages.detail.tags'))
                        ->state(self::tags($entry))
                        ->badge()
                        ->color('gray')
                        ->placeholder('-'),
                ]),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Section::make(__('filament-headroom::pages.detail.sections.tokens'))
                    ->columns(2)
                    ->schema([
                        self::tokens('estimated_input', $entry->estimatedInputTokens)
                            ->helperText(ValueBasis::Estimated->getLabel()),
                        self::tokens('actual_input', $entry->actualInputTokens)
                            ->placeholder(__('filament-headroom::pages.detail.no_usage')),
                        TextEntry::make('estimate_error')
                            ->label(__('filament-headroom::pages.detail.estimate_error'))
                            ->state(self::estimateError($entry))
                            ->placeholder('-'),
                        self::tokens('output', $entry->outputTokens),
                        self::tokens('cache_read', $entry->cacheReadTokens),
                        self::tokens('cache_write', $entry->cacheWriteTokens),
                        self::tokens('context_window', $entry->contextWindow),
                        TextEntry::make('percent_used')
                            ->label(__('filament-headroom::pages.requests.columns.context'))
                            ->state($entry->percentUsed === null ? null : Format::percent($entry->percentUsed))
                            ->placeholder('-'),
                    ]),
                Section::make(__('filament-headroom::pages.detail.sections.cost'))
                    ->columns(1)
                    ->schema([
                        TextEntry::make('actual_cost')
                            ->label(__('filament-headroom::pages.detail.actual_cost'))
                            ->state($entry->actualCost === null ? null : Cost::fromMoney($entry->actualCost))
                            ->html()
                            ->placeholder(__('filament-headroom::pages.detail.no_usage')),
                        TextEntry::make('estimated_cost')
                            ->label(__('filament-headroom::pages.detail.estimated_cost'))
                            ->state($entry->estimatedCost === null ? null : Cost::fromMoney($entry->estimatedCost))
                            ->helperText(__('filament-headroom::pages.detail.estimated_cost_help'))
                            ->html()
                            ->placeholder('-'),
                    ]),
            ]),
            $entry->breakdown === null ? null : Section::make(__('filament-headroom::pages.detail.sections.breakdown'))
                ->description(__('filament-headroom::pages.detail.breakdown_help'))
                ->columns(['default' => 2, 'md' => 5])
                ->schema(array_map(
                    fn (string $part): TextEntry => TextEntry::make("breakdown_{$part}")
                        ->label(__("filament-headroom::pages.detail.parts.{$part}"))
                        ->state(Format::number($entry->breakdown[$part] ?? 0)),
                    array_keys($entry->breakdown),
                )),
            $run === null ? null : Section::make(__('filament-headroom::pages.detail.sections.run'))
                ->description(trans_choice('filament-headroom::pages.detail.run_summary', $run->stepCount(), [
                    'count' => $run->stepCount(),
                    'agent' => $run->agent === null ? '-' : class_basename($run->agent),
                    'cost' => Cost::fromSpend($run->spend)->label(),
                ]))
                ->schema([
                    View::make('filament-headroom::run-steps')->viewData([
                        'steps' => self::steps($run, $entry->requestId),
                    ]),
                ]),
        ])));
    }

    /**
     * Each step with how much its estimate grew from the step before, the
     * context the run carried forward.
     *
     * @return list<array{id: string, url: string, number: int, current: bool, counted_at: ?string, model: ?string, outcome: string, outcome_color: string, estimated: ?string, growth: ?string, percent: ?string, cost: Cost}>
     */
    public static function steps(RunSummary $run, string $currentId): array
    {
        $steps = [];
        $previous = null;

        foreach ($run->steps as $index => $step) {
            $estimate = $step->estimatedInputTokens;
            $growth = $estimate === null || $previous === null ? null : $estimate - $previous;

            $steps[] = [
                'id' => $step->requestId,
                'url' => static::getUrl(['requestId' => $step->requestId]),
                'number' => $index + 1,
                'current' => $step->requestId === $currentId,
                'counted_at' => $step->countedAt?->format('H:i:s'),
                'model' => $step->model(),
                'outcome' => Outcomes::label($step->outcome),
                'outcome_color' => Outcomes::color($step->outcome),
                'estimated' => $estimate === null ? null : Format::number($estimate),
                'growth' => $growth === null ? null : ($growth >= 0 ? '+' : '').Format::number($growth),
                'percent' => $step->percentUsed === null ? null : Format::percent($step->percentUsed),
                'cost' => Cost::fromMoney($step->cost()),
            ];

            $previous = $estimate ?? $previous;
        }

        return $steps;
    }

    private static function tokens(string $name, ?int $value): TextEntry
    {
        return TextEntry::make($name)
            ->label(__("filament-headroom::pages.detail.{$name}"))
            ->state($value === null ? null : Format::number($value))
            ->placeholder('-');
    }

    /**
     * The model counted against, and the one that answered when failover
     * changed it.
     */
    private static function models(LedgerEntry $entry): ?string
    {
        if ($entry->modelUsed !== null && $entry->modelCounted !== null && $entry->modelUsed !== $entry->modelCounted) {
            return __('filament-headroom::pages.detail.failover', ['counted' => $entry->modelCounted, 'used' => $entry->modelUsed]);
        }

        $model = $entry->model();
        $provider = $entry->provider();

        if ($model === null) {
            return null;
        }

        return $provider === null ? $model : "{$model} ({$provider})";
    }

    /**
     * Signed tokens and share of the reported input: positive when the
     * estimate ran low.
     */
    private static function estimateError(LedgerEntry $entry): ?string
    {
        $error = $entry->estimateError();

        if ($error === null || $entry->actualInputTokens === null || $entry->actualInputTokens === 0) {
            return null;
        }

        return __('filament-headroom::pages.detail.estimate_error_value', [
            'tokens' => ($error > 0 ? '+' : '').Format::number($error),
            'percent' => Format::percent(abs($error) / $entry->actualInputTokens * 100),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function tags(LedgerEntry $entry): array
    {
        $plugin = HeadroomPlugin::current();
        $labels = [];

        foreach ($entry->tags as $key => $value) {
            $labels[] = $plugin?->getTagLabel($key, $value) ?? __('filament-headroom::tags.label', ['key' => $key, 'value' => $value]);
        }

        return $labels;
    }
}
