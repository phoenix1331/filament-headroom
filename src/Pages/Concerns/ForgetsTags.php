<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Phoenix1331\AiHeadroomLedger\Facades\Ledger;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

/**
 * The Requests page's data subject action: erase one context tag value from
 * the ledger with the ledger's own forgetTag(), which also clears it from
 * rollups and gives affected runs new invocation IDs. Shown only to users
 * allowForgettingTags() allows, never under tenant scoping.
 */
trait ForgetsTags
{
    protected function forgetTagAction(): Action
    {
        return Action::make('forgetTag')
            ->label(__('filament-headroom::pages.requests.forget.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => self::canForget())
            ->authorize(fn (): bool => self::canForget())
            ->modalHeading(__('filament-headroom::pages.requests.forget.heading'))
            ->modalDescription(__('filament-headroom::pages.requests.forget.description'))
            ->modalSubmitActionLabel(__('filament-headroom::pages.requests.forget.submit'))
            ->schema([
                Select::make('key')
                    ->label(__('filament-headroom::pages.requests.forget.key'))
                    ->options(fn (): array => array_combine(self::forgettableKeys(), self::forgettableKeys()))
                    ->required()
                    ->in(fn (): array => self::forgettableKeys()),
                TextInput::make('value')
                    ->label(__('filament-headroom::pages.requests.forget.value'))
                    ->required()
                    ->maxLength(191),
            ])
            ->requiresConfirmation()
            ->action(function (array $data): void {
                $key = is_string($data['key'] ?? null) ? $data['key'] : '';
                $value = is_string($data['value'] ?? null) ? $data['value'] : '';

                $count = Ledger::forgetTag($key, $value);

                Notification::make()
                    ->title(trans_choice('filament-headroom::pages.requests.forget.done', $count, ['count' => $count, 'key' => $key]))
                    ->success()
                    ->send();

                $this->resetTable();
            });
    }

    private static function canForget(): bool
    {
        return (HeadroomPlugin::current()?->canForgetTags() ?? false) && self::forgettableKeys() !== [];
    }

    /**
     * Every allowlisted tag key, including any not offered as a filter.
     *
     * @return list<string>
     */
    private static function forgettableKeys(): array
    {
        $keys = config('headroom-ledger.tags.allowed', []);

        return is_array($keys) ? array_values(array_filter($keys, is_string(...))) : [];
    }
}
