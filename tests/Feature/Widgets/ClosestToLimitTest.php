<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Overview;
use Phoenix1331\FilamentHeadroom\Pages\RequestDetail;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\ClosestToLimit;
use Phoenix1331\FilamentHeadroom\Widgets\ContextHeadroom;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

function atPercent(float $percent, int $daysAgo = 0, ?callable $state = null): string
{
    $factory = LedgerEntryFactory::new()
        ->forModel('gpt-5')
        ->at(CarbonImmutable::now()->setTime(12, 0)->subDays($daysAgo));

    return ($state === null ? $factory : $state($factory))
        ->create(['estimated_input_tokens' => (int) round(400_000 * $percent / 100)])
        ->request_id;
}

it('is one of the built-in widgets, right after the context chart', function (): void {
    $widgets = HeadroomPlugin::WIDGETS;

    expect($widgets)->toContain(ClosestToLimit::class)
        ->and(array_search(ClosestToLimit::class, $widgets, true))->toBe(array_search(ContextHeadroom::class, $widgets, true) + 1);
});

it('refuses to mount for users the plugin does not authorise', function (): void {
    HeadroomPlugin::get()->authorize(false);

    livewire(ClosestToLimit::class)->assertForbidden();
});

it('shows an empty state', function (): void {
    livewire(ClosestToLimit::class)->assertOk()->assertSee('No counted requests in this range');
});

it('lists the requests closest to their window, highest first', function (): void {
    $ids = array_map(fn (float $percent): string => atPercent($percent), [12.0, 91.5, 40.0, 88.0, 70.0, 99.0, 5.0]);

    livewire(ClosestToLimit::class)
        ->assertCanSeeTableRecords([$ids[5], $ids[1], $ids[3], $ids[4], $ids[2]], inOrder: true)
        ->assertCanNotSeeTableRecords([$ids[0], $ids[6]])
        ->assertSee('99%')
        ->assertSee('396,000 of 400,000 tokens');
});

it('leaves out blocked requests but keeps warned ones', function (): void {
    $blocked = atPercent(120.0, state: fn ($f) => $f->blocked());
    $warned = atPercent(85.0, state: fn ($f) => $f->warned());
    $normal = atPercent(30.0);

    livewire(ClosestToLimit::class)
        ->assertCanSeeTableRecords([$warned, $normal], inOrder: true)
        ->assertCanNotSeeTableRecords([$blocked])
        ->assertSee('Warned');
});

it('follows the page range', function (): void {
    $recent = atPercent(40.0);
    $older = atPercent(95.0, daysAgo: 10);

    livewire(ClosestToLimit::class, ['pageFilters' => ['range' => '7d']])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$older]);
});

it('links each row to its detail page when the Requests page is enabled', function (): void {
    $id = atPercent(50.0);

    livewire(ClosestToLimit::class)->assertSeeHtml(e(RequestDetail::getUrl(['requestId' => $id])));

    HeadroomPlugin::get()->pages([Overview::class]);

    livewire(ClosestToLimit::class)->assertDontSeeHtml('/headroom/requests/'.$id);
});
