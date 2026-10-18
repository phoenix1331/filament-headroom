<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\Enums\Range;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\EstimateAccuracy;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

function estimated(string $model, int $estimate, int $actual, int $daysAgo = 0, CountMethod $method = CountMethod::Estimated): void
{
    LedgerEntryFactory::new()
        ->forModel($model)
        ->countMethod($method)
        ->at(CarbonImmutable::now()->setTime(12, 0)->subDays($daysAgo))
        ->create(['estimated_input_tokens' => $estimate, 'actual_input_tokens' => $actual]);
}

describe('range buckets', function (): void {
    it('splits each range into consecutive buckets ending today', function (Range $range, int $count, string $first, string $firstEnd): void {
        $buckets = $range->buckets(CarbonImmutable::parse('2026-10-18 15:00'));

        expect($buckets)->toHaveCount($count)
            ->and($buckets[0][0]->toDateString())->toBe($first)
            ->and($buckets[0][1]->toDateString())->toBe($firstEnd)
            ->and(end($buckets)[1]->toDateString())->toBe('2026-10-18');

        foreach (array_slice($buckets, 1) as $index => $bucket) {
            expect($bucket[0]->toDateString())->toBe($buckets[$index][1]->addDay()->toDateString());
        }
    })->with([
        '7 days, daily' => [Range::SevenDays, 7, '2026-10-12', '2026-10-12'],
        '30 days, every 3 days' => [Range::ThirtyDays, 10, '2026-09-19', '2026-09-21'],
        '90 days, weekly with a short first week' => [Range::NinetyDays, 13, '2026-07-21', '2026-07-26'],
    ]);
});

it('is one of the built-in widgets', function (): void {
    expect(HeadroomPlugin::WIDGETS)->toContain(EstimateAccuracy::class);
});

it('refuses to mount for users the plugin does not authorise', function (): void {
    HeadroomPlugin::get()->authorize(false);

    livewire(EstimateAccuracy::class)->assertForbidden();
});

it('says when nothing reported usage', function (): void {
    LedgerEntryFactory::new()->blocked()->at(CarbonImmutable::now()->setTime(12, 0))->create();

    $component = livewire(EstimateAccuracy::class)->assertOk()->assertSee('No requests in this range reported usage');

    expect(chartData($component)['datasets'])->toBe([])
        ->and(chartData($component)['labels'])->toHaveCount(10);
});

it('plots weighted error per model per bucket with gaps where there are no samples', function (): void {
    estimated('gpt-5', 900, 1_000);
    estimated('gpt-5', 1_150, 1_000);
    estimated('gpt-5', 950, 1_000, daysAgo: 12);
    estimated('claude-haiku-5-5', 2_000, 2_000);

    $component = livewire(EstimateAccuracy::class)->assertSee('Lower is better');
    $series = seriesByLabel($component);

    expect(array_keys($series))->toBe(['gpt-5', 'claude-haiku-5-5'])
        ->and($series['gpt-5']['data'][9])->toBe(12.5)
        ->and($series['gpt-5']['samples'][9])->toBe(2)
        ->and($series['gpt-5']['data'][5])->toBe(5.0)
        ->and($series['gpt-5']['data'][0])->toBeNull()
        ->and($series['claude-haiku-5-5']['data'][9])->toBe(0.0)
        ->and($series['claude-haiku-5-5']['slot'])->toBe(0)
        ->and($series['gpt-5']['slot'])->toBe(1);
});

it('narrows to one count method', function (): void {
    estimated('gpt-5', 800, 1_000, method: CountMethod::Estimated);
    estimated('gpt-5', 1_000, 1_000, method: CountMethod::Native);
    estimated('claude-sonnet-5-5', 900, 1_000, method: CountMethod::Calibrated);

    $component = livewire(EstimateAccuracy::class);
    expect(seriesByLabel($component)['gpt-5']['data'][9])->toBe(10.0);

    $estimatedOnly = seriesByLabel($component->set('filter', CountMethod::Estimated->value));
    expect(array_keys($estimatedOnly))->toBe(['gpt-5'])
        ->and($estimatedOnly['gpt-5']['data'][9])->toBe(20.0);

    expect(array_keys(seriesByLabel($component->set('filter', CountMethod::Calibrated->value))))->toBe(['claude-sonnet-5-5']);
});

it('leaves failover answers out', function (): void {
    LedgerEntryFactory::new()->forModel('claude-sonnet-5-5')->failover('gpt-5')
        ->at(CarbonImmutable::now()->setTime(12, 0))
        ->create(['estimated_input_tokens' => 500, 'actual_input_tokens' => 1_000]);

    expect(chartData(livewire(EstimateAccuracy::class))['datasets'])->toBe([]);
});

it('uses daily points for seven days', function (): void {
    estimated('gpt-5', 900, 1_000);

    expect(chartData(livewire(EstimateAccuracy::class, ['pageFilters' => ['range' => '7d']]))['labels'])->toHaveCount(7);
});

it('offers every count method as a filter', function (): void {
    $filters = (fn () => $this->getFilters())->call(livewire(EstimateAccuracy::class)->instance());

    expect($filters)->toBe([
        'all' => 'All count methods',
        'native' => 'Native',
        'estimated' => 'Estimated',
        'calibrated' => 'Calibrated',
    ]);
});

it('builds attribute-safe options', function (): void {
    $options = (fn () => $this->getOptions())->call(livewire(EstimateAccuracy::class)->instance())->toHtml();

    expect($options)->toContain('spanGaps: false')->not->toContain('"');
});

it('reads every bucket from one grouped accuracy query', function (string $range): void {
    estimated('gpt-5', 900, 1_000);
    estimated('gpt-5', 900, 1_000, daysAgo: 20);

    $component = livewire(EstimateAccuracy::class, ['pageFilters' => ['range' => $range]]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    (fn (): array => $this->getData())->call($component->instance());

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
})->with(['7d', '30d', '90d']);
