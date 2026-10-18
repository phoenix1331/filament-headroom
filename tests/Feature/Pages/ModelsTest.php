<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Phoenix1331\AiHeadroom\Calibration\Calibrator;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Models;
use Phoenix1331\FilamentHeadroom\Pages\Overview;
use Phoenix1331\FilamentHeadroom\Support\CalibrationStatus;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\ModelsTable;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

function modelRequest(string $model, array $attributes = [], int $daysAgo = 0): void
{
    LedgerEntryFactory::new()
        ->forModel($model)
        ->countMethod(CountMethod::Estimated)
        ->at(CarbonImmutable::now()->setTime(12, 0)->subDays($daysAgo))
        ->create($attributes);
}

/**
 * @return array<string, array<string, mixed>>
 */
function modelRows(?string $sortColumn = 'requests', ?string $sortDirection = 'desc', array $pageFilters = []): array
{
    $widget = livewire(ModelsTable::class, ['pageFilters' => $pageFilters])->instance();

    return (fn (): array => $this->rows($sortColumn, $sortDirection))->call($widget);
}

describe('page', function (): void {
    it('is one of the built-in pages, registered at its own path', function (): void {
        expect(HeadroomPlugin::PAGES)->toContain(Models::class)
            ->and(Filament::getPanel('admin')->getPages())->toContain(Models::class)
            ->and(Route::has(Models::getRouteName()))->toBeTrue()
            ->and(Models::getUrl())->toEndWith('/admin/headroom/models');
    });

    it('refuses access by default outside local', function (): void {
        livewire(Models::class)->assertForbidden();
        livewire(ModelsTable::class)->assertForbidden();
    });

    it('opens for authorised users with the range picker and the models table', function (): void {
        HeadroomPlugin::get()->authorize(true);

        $page = livewire(Models::class)->assertOk()->assertSee('Models')->assertSet('filters.range', '30d');

        expect($page->instance()->getVisibleWidgets())->toBe([ModelsTable::class]);
    });

    it('shows the table even with every dashboard widget disabled', function (): void {
        HeadroomPlugin::get()->authorize(true)->widgets([]);

        expect(ModelsTable::canView())->toBeTrue();
    });

    it('sits after Overview in the Headroom group', function (): void {
        expect(Models::getNavigationGroup())->toBe('Headroom')
            ->and(Models::getNavigationSort())->toBeGreaterThan(Overview::getNavigationSort())
            ->and(Models::getNavigationLabel())->toBe('Models');
    });
});

describe('models table', function (): void {
    beforeEach(function (): void {
        HeadroomPlugin::get()->authorize(true);
    });

    it('shows an empty state', function (): void {
        livewire(ModelsTable::class)->assertOk()->assertSee('No requests in this range');
    });

    it('summarises each model', function (): void {
        modelRequest('gpt-5', ['actual_cost' => 1_000_000, 'estimated_input_tokens' => 40_000, 'actual_input_tokens' => 50_000]);
        modelRequest('gpt-5', ['actual_cost' => 2_000_000, 'estimated_input_tokens' => 120_000, 'actual_input_tokens' => 100_000]);
        LedgerEntryFactory::new()->forModel('gpt-5')->warned()->at(CarbonImmutable::now()->setTime(12, 0))->create(['used_at' => null]);
        LedgerEntryFactory::new()->forModel('claude-haiku-5-5')->blocked()->at(CarbonImmutable::now()->setTime(12, 0))->create();

        $rows = modelRows();

        expect(array_keys($rows))->toBe(['gpt-5', 'claude-haiku-5-5'])
            ->and($rows['gpt-5'])->toMatchArray([
                'requests' => 3,
                'blocked' => 0,
                'warned' => 1,
                'error' => 20.0,
                'bias' => -6.67,
                'samples' => 2,
                'average_context' => round((10.0 + 30.0 + $rows['gpt-5']['peak_context']) / 3, 2),
            ])
            ->and($rows['claude-haiku-5-5'])->toMatchArray(['requests' => 1, 'blocked' => 1, 'error' => null, 'samples' => 0])
            ->and($rows['claude-haiku-5-5']['spend']->basis->value)->toBe('estimated');

        livewire(ModelsTable::class)
            ->assertCanSeeTableRecords(['gpt-5', 'claude-haiku-5-5'], inOrder: true)
            ->assertSee('Estimate');
    });

    it('sorts by any sortable column', function (): void {
        modelRequest('a-model', ['estimated_input_tokens' => 900, 'actual_input_tokens' => 1_000]);
        modelRequest('b-model', ['estimated_input_tokens' => 500, 'actual_input_tokens' => 1_000]);
        LedgerEntryFactory::new()->forModel('b-model')->counted()->at(CarbonImmutable::now()->setTime(12, 0))->create();

        expect(array_keys(modelRows('requests', 'desc')))->toBe(['b-model', 'a-model'])
            ->and(array_keys(modelRows('model', 'asc')))->toBe(['a-model', 'b-model'])
            ->and(array_keys(modelRows('error', 'asc')))->toBe(['a-model', 'b-model'])
            ->and(array_keys(modelRows('error', 'desc')))->toBe(['b-model', 'a-model']);
    });

    it('keeps requests without a model in their own row', function (): void {
        LedgerEntryFactory::new()->counted()->at(CarbonImmutable::now()->setTime(12, 0))->create(['model_counted' => null, 'provider_counted' => null]);

        $rows = modelRows();

        expect($rows)->toHaveKey(ModelsTable::UNKNOWN_MODEL_KEY)
            ->and($rows[ModelsTable::UNKNOWN_MODEL_KEY]['model'])->toBe('Unknown model')
            ->and($rows[ModelsTable::UNKNOWN_MODEL_KEY]['calibration'])->toBeNull();
    });

    it('follows the page range', function (): void {
        modelRequest('gpt-5');
        modelRequest('claude-sonnet-5-5', daysAgo: 10);

        expect(array_keys(modelRows(pageFilters: ['range' => '7d'])))->toBe(['gpt-5']);
    });
});

describe('calibration status', function (): void {
    it('is off when calibration is disabled', function (): void {
        config(['ai-headroom.calibration.enabled' => false]);

        $status = CalibrationStatus::for('gpt-5');

        expect($status->state)->toBe(CalibrationStatus::OFF)
            ->and($status->label())->toBe('Off')
            ->and($status->color())->toBe('gray');
    });

    it('has no data before any usage is observed', function (): void {
        expect(CalibrationStatus::for('gpt-5')->label())->toBe('No data yet');
    });

    it('is learning until it has the minimum samples', function (): void {
        config(['ai-headroom.calibration.min_samples' => 20]);
        app(Calibrator::class)->observe('gpt-5', 1_000, 1_100);
        app(Calibrator::class)->observe('gpt-5', 1_000, 1_100);

        $status = CalibrationStatus::for('gpt-5');

        expect($status->state)->toBe(CalibrationStatus::LEARNING)
            ->and($status->label())->toBe('Learning (2 of 20 samples)')
            ->and($status->color())->toBe('info');
    });

    it('is active with its correction once calibrated', function (): void {
        config(['ai-headroom.calibration.min_samples' => 1]);
        app()->forgetInstance(Calibrator::class);
        app(Calibrator::class)->observe('gpt-5', 1_000, 1_100);

        $status = CalibrationStatus::for('gpt-5');

        expect($status->state)->toBe(CalibrationStatus::ACTIVE)
            ->and($status->factor)->toBe(1.1)
            ->and($status->label())->toStartWith('Active: x1.1, +')
            ->and($status->color())->toBe('success');
    });

    it('shows in the models table', function (): void {
        HeadroomPlugin::get()->authorize(true);
        modelRequest('gpt-5');

        livewire(ModelsTable::class)->assertSee('No data yet');
    });
});
