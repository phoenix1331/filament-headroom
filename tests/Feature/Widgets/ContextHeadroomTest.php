<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\ContextHeadroom;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

function usedContext(float $percent, int $daysAgo = 0): void
{
    LedgerEntryFactory::new()
        ->forModel('gpt-5')
        ->at(CarbonImmutable::now()->setTime(12, 0)->subDays($daysAgo))
        ->create(['estimated_input_tokens' => (int) round(400_000 * $percent / 100)]);
}

it('is one of the built-in widgets', function (): void {
    expect(HeadroomPlugin::WIDGETS)->toContain(ContextHeadroom::class);
});

it('refuses to mount for users the plugin does not authorise', function (): void {
    HeadroomPlugin::get()->authorize(false);

    livewire(ContextHeadroom::class)->assertForbidden();
});

it('says when nothing was counted', function (): void {
    $component = livewire(ContextHeadroom::class)->assertOk()->assertSee('No counted requests in this range.');

    expect(chartData($component)['datasets'])->toBe([]);
});

it('puts every request in exactly one bucket, edges inclusive', function (): void {
    foreach ([0.0, 10.0, 24.99, 25.0, 49.0, 50.0, 80.0, 89.99, 90.0, 95.0, 100.0, 130.0] as $percent) {
        usedContext($percent);
    }

    $data = chartData(livewire(ContextHeadroom::class));

    expect($data['labels'])->toBe(['0-25%', '25-50%', '50-75%', '75-90%', '90-100%', '100%+'])
        ->and($data['datasets'][0]['data'])->toBe([3, 2, 1, 2, 2, 2])
        ->and(array_sum($data['datasets'][0]['data']))->toBe(12);
});

it('counts blocked requests over the window', function (): void {
    LedgerEntryFactory::new()->blocked()->at(CarbonImmutable::now()->setTime(12, 0))->count(2)->create();

    expect(chartData(livewire(ContextHeadroom::class))['datasets'][0]['data'][5])->toBe(2);
});

it('calls out requests near the limit', function (array $percents, string $message): void {
    foreach ($percents as $percent) {
        usedContext($percent);
    }

    livewire(ContextHeadroom::class)->assertSee($message);
})->with([
    'none' => [[10.0], 'No requests reached 90%.'],
    'one' => [[10.0, 92.0], '1 request reached 90% or more.'],
    'several' => [[91.0, 99.0, 120.0], '3 requests reached 90% or more.'],
]);

it('follows the page range', function (): void {
    usedContext(10.0);
    usedContext(95.0, daysAgo: 10);

    expect(chartData(livewire(ContextHeadroom::class, ['pageFilters' => ['range' => '7d']]))['datasets'][0]['data'])->toBe([1, 0, 0, 0, 0, 0]);
});

it('builds attribute-safe options for a single unlabelled series', function (): void {
    $options = (fn () => $this->getOptions())->call(livewire(ContextHeadroom::class)->instance())->toHtml();

    expect($options)->toContain('legend: { display: false }')->not->toContain('"');
});
