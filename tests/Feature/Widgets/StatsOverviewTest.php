<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\StatsOverview;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

/**
 * Pinned to today at $hour, so the factory's random date never lands outside the range.
 */
function todayAt(int $hour = 12): CarbonImmutable
{
    return CarbonImmutable::now()->setTime($hour, 0);
}

/**
 * A completed request with an exact reported cost, $daysAgo days back at noon.
 */
function billed(int $microUnits, int $daysAgo = 0, string $currency = 'USD'): void
{
    LedgerEntryFactory::new()
        ->at(todayAt()->subDays($daysAgo))
        ->inCurrency($currency)
        ->create(['actual_cost' => $microUnits, 'percent_used' => 40.0, 'estimated_input_tokens' => null]);
}

it('is one of the built-in widgets', function (): void {
    expect(HeadroomPlugin::WIDGETS)->toContain(StatsOverview::class);
});

it('refuses to mount for users the plugin does not authorise', function (): void {
    HeadroomPlugin::get()->authorize(false);

    livewire(StatsOverview::class)->assertForbidden();
});

it('refuses to mount when disabled through widgets()', function (): void {
    HeadroomPlugin::get()->widgets([]);

    livewire(StatsOverview::class)->assertForbidden();
});

it('shows an empty ledger without errors', function (): void {
    livewire(StatsOverview::class)
        ->assertOk()
        ->assertSee('No cost recorded')
        ->assertSee('No data')
        ->assertDontSee('previous 30 days')
        ->assertDontSee('fi-badge');
});

it('summarises the selected range', function (): void {
    billed(1_000_000);
    billed(1_500_000, daysAgo: 3);
    LedgerEntryFactory::new()->warned()->at(todayAt(9))->create(['actual_cost' => 500_000]);
    LedgerEntryFactory::new()->blocked()->at(todayAt(10))->create();
    LedgerEntryFactory::new()->blocked()->at(todayAt(11))->create();

    livewire(StatsOverview::class)
        ->assertSeeInOrder(['Spend', 'Requests', '5', '3 with usage reported', 'Average context used', 'Blocked', '2', 'Warned', '1']);
});

it('labels spend that includes estimates and never shows it as billed', function (): void {
    billed(1_000_000);
    LedgerEntryFactory::new()->blocked()->at(todayAt())->create();

    livewire(StatsOverview::class)
        ->assertSee('Includes estimates')
        ->assertSee('fi-badge', escape: false);
});

it('labels spend that is wholly estimated', function (): void {
    LedgerEntryFactory::new()->blocked()->at(todayAt())->count(2)->create();

    livewire(StatsOverview::class)
        ->assertSee('Estimate')
        ->assertDontSee('Includes estimates');
});

it('labels approximate cache pricing', function (): void {
    LedgerEntryFactory::new()->approximateCost()->at(todayAt())->create();

    livewire(StatsOverview::class)->assertSee('Approximate');
});

it('compares spend with the previous period', function (int $previous, string $trend): void {
    billed(3_000_000);
    billed($previous, daysAgo: 40);
    billed(9_000_000, daysAgo: 70);

    livewire(StatsOverview::class)->assertSee('$3.00')->assertSee($trend);
})->with([
    'up' => [2_000_000, '50% more than the previous 30 days'],
    'down' => [6_000_000, '50% less than the previous 30 days'],
    'flat' => [3_000_000, 'Same as the previous 30 days'],
]);

it('uses the page range filter', function (): void {
    billed(1_000_000);
    billed(2_000_000, daysAgo: 10);

    livewire(StatsOverview::class, ['pageFilters' => ['range' => '7d']])
        ->assertSee('$1.00')
        ->assertSee('50% less than the previous 7 days');

    livewire(StatsOverview::class, ['pageFilters' => ['range' => '30d']])
        ->assertSee('$3.00');
});

it('falls back to the plugin default range', function (): void {
    billed(1_000_000);
    billed(2_000_000, daysAgo: 60);
    HeadroomPlugin::get()->defaultRange('90d');

    livewire(StatsOverview::class)->assertSee('$3.00')->assertSee('previous 90 days');
});

it('keeps currencies apart and skips the trend', function (): void {
    billed(1_000_000);
    billed(2_000_000, currency: 'EUR');
    billed(1_000_000, daysAgo: 40);

    livewire(StatsOverview::class)
        ->assertSee('€2.00 + $1.00')
        ->assertDontSee('previous 30 days');
});

it('treats a currency new to this period as new spend', function (): void {
    billed(1_000_000);
    billed(1_000_000, daysAgo: 40, currency: 'EUR');

    livewire(StatsOverview::class)->assertSee('No spend in the previous 30 days');
});
