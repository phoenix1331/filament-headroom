<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\AiHeadroomLedger\Enums\Outcome;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Support\Outcomes;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\BlockedWarnedFailed;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

function minutesAgo(int $minutes): CarbonImmutable
{
    return CarbonImmutable::now()->subMinutes($minutes);
}

describe('outcomes', function (): void {
    it('labels and colours every outcome', function (Outcome $outcome, string $label, string $color): void {
        expect(Outcomes::label($outcome))->toBe($label)
            ->and(Outcomes::color($outcome))->toBe($color);
    })->with([
        [Outcome::Counted, 'Counted', 'gray'],
        [Outcome::Completed, 'Completed', 'success'],
        [Outcome::Warned, 'Warned', 'warning'],
        [Outcome::Blocked, 'Blocked', 'danger'],
        [Outcome::Failed, 'Failed', 'danger'],
    ]);

    it('turns recorded reasons into words', function (?string $reason, ?string $expected): void {
        expect(Outcomes::reason($reason))->toBe($expected);
    })->with([
        'context window' => ['context_window', 'Did not fit the context window'],
        'percent of context' => ['max_percent_of_context', 'Over the context percentage limit'],
        'cost' => ['max_cost', 'Over the cost limit'],
        'exception with status' => ['Illuminate\\Http\\Client\\RequestException:529', 'RequestException (HTTP 529)'],
        'exception alone' => ['Illuminate\\Http\\Client\\ConnectionException', 'ConnectionException'],
        'exception with empty status' => ['App\\Ai\\TimeoutException:', 'TimeoutException'],
        'none' => [null, null],
        'empty' => ['', null],
    ]);
});

it('is one of the built-in widgets', function (): void {
    expect(HeadroomPlugin::WIDGETS)->toContain(BlockedWarnedFailed::class);
});

it('refuses to mount for users the plugin does not authorise', function (): void {
    HeadroomPlugin::get()->authorize(false);

    livewire(BlockedWarnedFailed::class)->assertForbidden();
});

it('shows an empty state', function (): void {
    LedgerEntryFactory::new()->at(minutesAgo(5))->create();

    livewire(BlockedWarnedFailed::class)
        ->assertOk()
        ->assertSee('No blocked, warned or failed requests in this range');
});

it('lists blocked, warned and failed requests with readable reasons, newest first', function (): void {
    $blocked = LedgerEntryFactory::new()->blocked()->forAgent('App\\Ai\\Agents\\SupportAgent')->forModel('gpt-5')->at(minutesAgo(30))->create();
    $warned = LedgerEntryFactory::new()->warned()->at(minutesAgo(20))->create();
    $failed = LedgerEntryFactory::new()->failed()->at(minutesAgo(10))->create();
    $completed = LedgerEntryFactory::new()->at(minutesAgo(5))->create();

    livewire(BlockedWarnedFailed::class)
        ->assertCanSeeTableRecords([$failed->request_id, $warned->request_id, $blocked->request_id], inOrder: true)
        ->assertCanNotSeeTableRecords([$completed->request_id])
        ->assertSeeInOrder(['Failed', 'RequestException (HTTP 529)', 'Warned', 'Over the context percentage limit', 'Blocked', 'Did not fit the context window'])
        ->assertSee('SupportAgent')
        ->assertSee('gpt-5');
});

it('shows at most the latest ten', function (): void {
    $entries = LedgerEntryFactory::new()->blocked()
        ->count(12)
        ->sequence(fn ($sequence): array => ['counted_at' => minutesAgo(100 - $sequence->index)])
        ->create();

    $sorted = $entries->sortByDesc('counted_at')->pluck('request_id');

    livewire(BlockedWarnedFailed::class)
        ->assertCanSeeTableRecords($sorted->take(BlockedWarnedFailed::LIMIT)->all(), inOrder: true)
        ->assertCanNotSeeTableRecords($sorted->slice(BlockedWarnedFailed::LIMIT)->all());
});

it('follows the page range', function (): void {
    $recent = LedgerEntryFactory::new()->blocked()->at(minutesAgo(5))->create();
    $older = LedgerEntryFactory::new()->blocked()->at(CarbonImmutable::now()->subDays(10))->create();

    livewire(BlockedWarnedFailed::class, ['pageFilters' => ['range' => '7d']])
        ->assertCanSeeTableRecords([$recent->request_id])
        ->assertCanNotSeeTableRecords([$older->request_id]);
});
