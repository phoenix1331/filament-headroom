<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Livewire\Features\SupportTesting\Testable;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Support\ChartPalette;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\SpendOverTime;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

function spendOn(string $model, int $microUnits, int $daysAgo = 0, string $currency = 'USD'): void
{
    LedgerEntryFactory::new()
        ->forModel($model)
        ->inCurrency($currency)
        ->at(CarbonImmutable::now()->setTime(12, 0)->subDays($daysAgo))
        ->create(['actual_cost' => $microUnits]);
}

/**
 * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
 */
function chartData(Testable $component): array
{
    $widget = $component->instance();

    return (fn (): array => $this->getCachedData())->call($widget);
}

/**
 * @return array<string, array<string, mixed>>
 */
function seriesByLabel(Testable $component): array
{
    return collect(chartData($component)['datasets'])->keyBy('label')->all();
}

it('is one of the built-in widgets', function (): void {
    expect(HeadroomPlugin::WIDGETS)->toContain(SpendOverTime::class);
});

it('refuses to mount for users the plugin does not authorise', function (): void {
    HeadroomPlugin::get()->authorize(false);

    livewire(SpendOverTime::class)->assertForbidden();
});

it('draws every day of the range even without spend', function (): void {
    $component = livewire(SpendOverTime::class)->assertOk()->assertSee('No spend in this range.');

    expect(chartData($component)['labels'])->toHaveCount(30)
        ->and(chartData($component)['labels'][29])->toBe(CarbonImmutable::today()->translatedFormat('j M'))
        ->and(chartData($component)['datasets'])->toBe([]);
});

it('follows the page range', function (): void {
    spendOn('gpt-5', 1_000_000);

    expect(chartData(livewire(SpendOverTime::class, ['pageFilters' => ['range' => '7d']]))['labels'])->toHaveCount(7)
        ->and(chartData(livewire(SpendOverTime::class, ['pageFilters' => ['range' => '90d']]))['labels'])->toHaveCount(90);
});

it('draws daily spend per model', function (): void {
    spendOn('claude-sonnet-5-5', 1_000_000);
    spendOn('claude-sonnet-5-5', 2_000_000, daysAgo: 5);
    spendOn('gpt-5', 4_000_000);

    $series = seriesByLabel(livewire(SpendOverTime::class));

    expect(array_keys($series))->toBe(['gpt-5', 'claude-sonnet-5-5'])
        ->and($series['claude-sonnet-5-5']['data'][29])->toBe(1.0)
        ->and($series['claude-sonnet-5-5']['data'][24])->toBe(2.0)
        ->and(array_sum($series['claude-sonnet-5-5']['data']))->toBe(3.0)
        ->and($series['gpt-5']['data'][29])->toBe(4.0)
        ->and($series['gpt-5']['estimated'])->toBeFalse();
});

it('keeps sub-cent spend exact', function (): void {
    spendOn('gemini-2.5-flash', 2_500);

    expect(seriesByLabel(livewire(SpendOverTime::class))['gemini-2.5-flash']['data'][29])->toBe(0.0025);
});

it('draws daily spend per provider', function (): void {
    spendOn('claude-sonnet-5-5', 1_000_000);
    spendOn('claude-haiku-5-5', 1_000_000);
    spendOn('gpt-5', 500_000);

    $series = seriesByLabel(livewire(SpendOverTime::class)->set('filter', SpendOverTime::BY_PROVIDER));

    expect(array_keys($series))->toBe(['anthropic', 'openai'])
        ->and($series['anthropic']['data'][29])->toBe(2.0);
});

it('folds series beyond the palette into Other so the lines add up', function (): void {
    foreach (range(1, 8) as $i) {
        spendOn("model-{$i}", $i * 1_000_000);
    }

    $series = seriesByLabel(livewire(SpendOverTime::class));

    expect($series)->toHaveCount(ChartPalette::slots() + 1)
        ->and($series)->not->toHaveKeys(['model-1', 'model-2'])
        ->and($series['Other']['data'][29])->toBe(3.0)
        ->and($series['Other']['slot'])->toBeNull()
        ->and(array_sum(array_map(fn (array $dataset): float => $dataset['data'][29], $series)))->toBe(36.0);
});

it('gives colours by name, not by spend rank', function (): void {
    spendOn('b-model', 5_000_000);
    spendOn('a-model', 1_000_000);

    $series = seriesByLabel(livewire(SpendOverTime::class));

    expect($series['a-model']['slot'])->toBe(0)
        ->and($series['b-model']['slot'])->toBe(1);
});

it('marks series that include estimates and says so', function (): void {
    spendOn('gpt-5', 1_000_000);
    LedgerEntryFactory::new()->forModel('claude-haiku-5-5')->blocked()->at(CarbonImmutable::now()->setTime(12, 0))->create();

    $component = livewire(SpendOverTime::class)->assertSee('Includes estimates for requests that never reported usage.');
    $series = seriesByLabel($component);

    expect($series['claude-haiku-5-5']['estimated'])->toBeTrue()
        ->and($series['gpt-5']['estimated'])->toBeFalse();
});

it('marks Other when a folded series includes estimates', function (): void {
    foreach (range(1, 6) as $i) {
        spendOn("model-{$i}", 10_000_000);
    }
    LedgerEntryFactory::new()->forModel('tiny')->blocked()->at(CarbonImmutable::now()->setTime(12, 0))->create(['estimated_cost' => 1_000]);

    $series = seriesByLabel(livewire(SpendOverTime::class));

    expect($series['Other']['estimated'])->toBeTrue();
});

it('draws the largest currency and names the others', function (): void {
    spendOn('gpt-5', 5_000_000);
    spendOn('gpt-5', 1_000_000, currency: 'EUR');

    $component = livewire(SpendOverTime::class)
        ->assertSee('Showing USD only; spend in EUR is not converted or included.');

    expect(seriesByLabel($component)['gpt-5']['data'][29])->toBe(5.0);
});

it('formats the axis and tooltips in the drawn currency with the validated palette', function (): void {
    spendOn('gpt-5', 1_000_000, currency: 'EUR');

    $widget = livewire(SpendOverTime::class)->instance();
    $options = (fn () => $this->getOptions())->call($widget)->toHtml();

    expect($options)->toContain("currency: 'EUR'")
        ->toContain(ChartPalette::LIGHT[0])
        ->toContain(ChartPalette::DARK[0])
        ->toContain('includes estimates')
        ->not->toContain('"');

});
