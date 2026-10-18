<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Requests;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

/**
 * An agent run of $steps steps a minute apart, starting $minutesAgo ago.
 * Returns its invocation ID, random so order never comes from the ID.
 *
 * @param  list<callable>  $states  Optional factory state per step.
 * @param  array<string, mixed>  $attributes
 */
function run(int $steps, int $minutesAgo, string $agent = 'App\\Ai\\Agents\\SupportAgent', array $states = [], array $attributes = []): string
{
    $id = (string) Str::uuid();

    foreach (range(0, $steps - 1) as $step) {
        $factory = LedgerEntryFactory::new()->at(CarbonImmutable::now()->subMinutes($minutesAgo - $step));
        $factory = isset($states[$step]) ? $states[$step]($factory) : $factory;
        $factory->create(['invocation_id' => $id, 'agent' => $agent, ...$attributes]);
    }

    return $id;
}

function runsView(): Testable
{
    return Livewire::withQueryParams(['group' => 'run'])->test(Requests::class);
}

it('switches between requests and runs from the header', function (): void {
    livewire(Requests::class)
        ->assertActionExists('groupByRun')
        ->assertActionHasUrl('groupByRun', Requests::getUrl(['group' => 'run']))
        ->assertSee('Group by run');

    runsView()
        ->assertSee('Agent runs')
        ->assertActionExists('ungroup')
        ->assertActionHasUrl('ungroup', Requests::getUrl());
});

it('shows one row per run with totals, newest first', function (): void {
    $older = run(3, 60, attributes: ['actual_input_tokens' => 1_000, 'output_tokens' => 100, 'actual_cost' => 500_000]);
    $newer = run(2, 10, 'App\\Ai\\Agents\\ResearchAgent', [1 => fn ($f) => $f->blocked()]);

    $component = runsView()
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertSee('ResearchAgent')
        ->assertSee('1 warned, blocked or failed')
        ->assertSee('$1.50');

    $rows = $component->instance()->getTableRecords()->getCollection();

    expect($rows[$older])->toMatchArray(['steps' => 3, 'input_tokens' => 3_000, 'output_tokens' => 300, 'problems' => 0])
        ->and($rows[$older]['agent'])->toBe('App\\Ai\\Agents\\SupportAgent')
        ->and($rows[$older]['started_at']->lessThan($rows[$older]['ended_at']))->toBeTrue()
        ->and($rows[$newer])->toMatchArray(['steps' => 2, 'problems' => 1])
        ->and($rows[$newer]['peak_context'])->toBeGreaterThan(100.0);
});

it('orders runs by most recent activity, not by ID', function (): void {
    $ids = array_map(fn (int $minutesAgo): string => run(2, $minutesAgo), [50, 5, 30, 90, 15]);

    runsView()->assertCanSeeTableRecords([$ids[1], $ids[4], $ids[2], $ids[0], $ids[3]], inOrder: true);
});

it('orders runs past the first scan batch', function (): void {
    $old = run(1, 600);
    LedgerEntryFactory::new()->count(Requests::RUN_SCAN_BATCH + 5)->at(CarbonImmutable::now()->subMinutes(30))->create(['invocation_id' => $busy = (string) Str::uuid()]);
    $recent = run(1, 5);

    runsView()->assertCanSeeTableRecords([$recent, $busy, $old], inOrder: true);
});

it('applies the request filters to runs', function (): void {
    $support = run(2, 30);
    $research = run(2, 20, 'App\\Ai\\Agents\\ResearchAgent');

    runsView()
        ->filterTable('agent', 'App\\Ai\\Agents\\ResearchAgent')
        ->assertCanSeeTableRecords([$research])
        ->assertCanNotSeeTableRecords([$support]);
});

it('counts only the steps that match the filters', function (): void {
    $id = run(3, 30, states: [2 => fn ($f) => $f->blocked()]);

    $rows = runsView()->filterTable('outcome', ['blocked'])->instance()->getTableRecords()->getCollection();

    expect($rows[$id]['steps'])->toBe(1);
});

it('sorts runs by their totals', function (): void {
    $short = run(1, 30);
    $long = run(4, 20);
    $medium = run(2, 10);

    runsView()
        ->sortTable('steps', 'desc')
        ->assertCanSeeTableRecords([$long, $medium, $short], inOrder: true)
        ->sortTable('steps', 'asc')
        ->assertCanSeeTableRecords([$short, $medium, $long], inOrder: true);
});

it('sorts runs by cost', function (): void {
    $cheap = run(1, 30, attributes: ['actual_cost' => 1_000]);
    $dear = run(1, 20, attributes: ['actual_cost' => 9_000_000]);

    runsView()
        ->sortTable('cost', 'desc')
        ->assertCanSeeTableRecords([$dear, $cheap], inOrder: true);
});

it('pages through runs', function (): void {
    $ids = array_reverse(array_map(fn (int $i): string => run(1, 100 - $i), range(1, 12)));

    runsView()
        ->set('tableRecordsPerPage', 10)
        ->assertCanSeeTableRecords(array_slice($ids, 0, 10), inOrder: true)
        ->call('gotoPage', 2)
        ->assertCanSeeTableRecords(array_slice($ids, 10), inOrder: true)
        ->assertCanNotSeeTableRecords(array_slice($ids, 0, 10));
});

it('shows an empty state for runs', function (): void {
    runsView()->assertSee('No agent runs in this range');
});

it('shows no tokens rather than zero for runs that never reported usage', function (): void {
    $id = run(1, 5, states: [fn ($f) => $f->blocked()]);

    $rows = runsView()->instance()->getTableRecords()->getCollection();

    expect($rows[$id]['input_tokens'])->toBeNull()
        ->and($rows[$id]['output_tokens'])->toBeNull();
});
