<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Models;
use Phoenix1331\FilamentHeadroom\Pages\Overview;
use Phoenix1331\FilamentHeadroom\Pages\Requests;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function requestAt(int $minutesAgo, array $attributes = [], ?callable $state = null): string
{
    $factory = LedgerEntryFactory::new()->at(CarbonImmutable::now()->subMinutes($minutesAgo));

    return ($state === null ? $factory : $state($factory))->create($attributes)->request_id;
}

describe('page', function (): void {
    it('is one of the built-in pages, between Overview and Models', function (): void {
        expect(HeadroomPlugin::PAGES)->toContain(Requests::class)
            ->and(Filament::getPanel('admin')->getPages())->toContain(Requests::class)
            ->and(Route::has(Requests::getRouteName()))->toBeTrue()
            ->and(Requests::getUrl())->toEndWith('/admin/headroom/requests')
            ->and(Requests::getNavigationSort())->toBeGreaterThan(Overview::getNavigationSort())
            ->and(Requests::getNavigationSort())->toBeLessThan(Models::getNavigationSort())
            ->and(Requests::getNavigationGroup())->toBe('Headroom');
    });

    it('refuses access by default outside local', function (): void {
        livewire(Requests::class)->assertForbidden();
    });
});

describe('table', function (): void {
    beforeEach(function (): void {
        HeadroomPlugin::get()->authorize(true);
    });

    it('shows an empty state', function (): void {
        livewire(Requests::class)->assertOk()->assertSee('No requests in this range');
    });

    it('lists requests newest first with their details', function (): void {
        $older = requestAt(30, ['agent' => 'App\\Ai\\Agents\\SupportAgent', 'actual_cost' => 1_250_000], fn ($f) => $f->forModel('gpt-5')->countMethod(CountMethod::Calibrated));
        $newer = requestAt(5, [], fn ($f) => $f->blocked());

        livewire(Requests::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertSee('SupportAgent')
            ->assertSee('gpt-5')
            ->assertSee('openai, calibrated count')
            ->assertSee('$1.25')
            ->assertSee('Blocked');
    });

    it('labels estimated input and estimated cost', function (): void {
        requestAt(5, [], fn ($f) => $f->blocked());

        livewire(Requests::class)
            ->assertSeeInOrder(['Input', 'Estimate'])
            ->assertSee('fi-badge', escape: false);
    });

    it('pages through the ledger', function (): void {
        $ids = collect(range(1, 30))->map(fn (int $i): string => requestAt($i))->all();

        livewire(Requests::class)
            ->assertCanSeeTableRecords(array_slice($ids, 0, 25), inOrder: true)
            ->assertCanNotSeeTableRecords(array_slice($ids, 25))
            ->call('gotoPage', 2)
            ->assertCanSeeTableRecords(array_slice($ids, 25), inOrder: true)
            ->assertCanNotSeeTableRecords(array_slice($ids, 0, 25));
    });

    it('changes the page size', function (): void {
        $ids = collect(range(1, 12))->map(fn (int $i): string => requestAt($i))->all();

        livewire(Requests::class)
            ->set('tableRecordsPerPage', 10)
            ->assertCanSeeTableRecords(array_slice($ids, 0, 10))
            ->assertCanNotSeeTableRecords(array_slice($ids, 10));
    });

    it('sorts through the ledger', function (string $column, array $attributes): void {
        $low = requestAt(10, $attributes[0]);
        $high = requestAt(20, $attributes[1]);
        $missing = requestAt(5, $attributes[2] ?? $attributes[0], fn ($f) => $column === 'output_tokens' ? $f->blocked() : $f);

        livewire(Requests::class)
            ->sortTable($column, 'desc')
            ->assertCanSeeTableRecords($column === 'output_tokens' ? [$high, $low, $missing] : [$high, $low], inOrder: true)
            ->sortTable($column, 'asc')
            ->assertCanSeeTableRecords($column === 'output_tokens' ? [$low, $high, $missing] : [$low, $high], inOrder: true);
    })->with([
        'input tokens' => ['input_tokens', [['actual_input_tokens' => 1_000], ['actual_input_tokens' => 90_000]]],
        'output tokens, missing last' => ['output_tokens', [['output_tokens' => 100], ['output_tokens' => 1_900], []]],
        'context' => ['percent_used', [['estimated_input_tokens' => 1_000, 'percent_used' => null], ['estimated_input_tokens' => 150_000, 'percent_used' => null]]],
        'cost' => ['cost', [['actual_cost' => 1_000], ['actual_cost' => 9_000_000]]],
    ]);

    it('sorts by time in both directions', function (): void {
        $old = requestAt(60);
        $new = requestAt(1);

        livewire(Requests::class)
            ->sortTable('counted_at', 'asc')
            ->assertCanSeeTableRecords([$old, $new], inOrder: true);
    });

    it('shows the plugin default range', function (): void {
        $recent = requestAt(5);
        $older = requestAt(60 * 24 * 10);

        HeadroomPlugin::get()->defaultRange('7d');

        livewire(Requests::class)
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$older]);
    });
});
