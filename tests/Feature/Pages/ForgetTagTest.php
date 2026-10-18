<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\GenericUser;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\AiHeadroomLedger\Facades\Ledger;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Requests;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\Team;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    config(['headroom-ledger.tags.allowed' => ['user_id', 'tenant_id']]);
    HeadroomPlugin::get()->authorize(true);

    LedgerEntryFactory::new()->count(3)->at(CarbonImmutable::now()->subMinutes(5))->withTags(['user_id' => 5, 'tenant_id' => 1])->create();
    LedgerEntryFactory::new()->count(2)->at(CarbonImmutable::now()->subMinutes(5))->withTags(['user_id' => 6, 'tenant_id' => 1])->create();
});

describe('availability', function (): void {
    it('is hidden by default, even to users who may see the dashboard', function (): void {
        expect(HeadroomPlugin::get()->canForgetTags())->toBeFalse();

        livewire(Requests::class)->assertActionHidden('forgetTag');
    });

    it('shows for users the separate callback allows', function (): void {
        HeadroomPlugin::get()->allowForgettingTags(fn ($user): bool => $user?->getAuthIdentifier() === 1);

        livewire(Requests::class)->assertActionHidden('forgetTag');

        $this->actingAs(new GenericUser(['id' => 1]));
        livewire(Requests::class)->assertActionVisible('forgetTag');
    });

    it('needs exactly true from the callback', function (): void {
        HeadroomPlugin::get()->allowForgettingTags(fn (): int => 1);

        livewire(Requests::class)->assertActionHidden('forgetTag');
    });

    it('is never available under tenant scoping', function (): void {
        HeadroomPlugin::get()->allowForgettingTags()->scopeToTenant('tenant_id');
        Filament::setTenant(Team::make(1), isQuiet: true);

        expect(HeadroomPlugin::get()->canForgetTags())->toBeFalse();

        livewire(Requests::class)->assertActionHidden('forgetTag');
    });

    it('is hidden when the ledger records no tags', function (): void {
        config(['headroom-ledger.tags.allowed' => []]);
        HeadroomPlugin::get()->allowForgettingTags();

        livewire(Requests::class)->assertActionHidden('forgetTag');
    });
});

describe('forgetting', function (): void {
    beforeEach(function (): void {
        HeadroomPlugin::get()->allowForgettingTags();
    });

    it('erases one tag value through the ledger and reports how many entries carried it', function (): void {
        livewire(Requests::class)
            ->callAction('forgetTag', data: ['key' => 'user_id', 'value' => '5'])
            ->assertHasNoActionErrors()
            ->assertNotified('Forgot user_id on 3 entries.');

        expect(Ledger::query()->forTag('user_id', 5)->count())->toBe(0)
            ->and(Ledger::query()->forTag('user_id', 6)->count())->toBe(2)
            ->and(Ledger::query()->forTag('tenant_id', 1)->count())->toBe(5);
    });

    it('says when no entries carried the value', function (): void {
        livewire(Requests::class)
            ->callAction('forgetTag', data: ['key' => 'user_id', 'value' => '404'])
            ->assertNotified('No entries carried user_id with that value.');
    });

    it('requires an allowlisted key and a value', function (): void {
        livewire(Requests::class)
            ->callAction('forgetTag', data: ['key' => 'email', 'value' => ''])
            ->assertHasActionErrors(['key' => 'in', 'value' => 'required']);

        expect(Ledger::query()->forTag('user_id', 5)->count())->toBe(3);
    });

    it('refuses to run for users who may not forget tags', function (): void {
        HeadroomPlugin::get()->allowForgettingTags(false);

        $page = livewire(Requests::class)->assertActionHidden('forgetTag');

        expect($page->instance()->getAction('forgetTag')->isAuthorized())->toBeFalse()
            ->and(Ledger::query()->forTag('user_id', 5)->count())->toBe(3);
    });
});
