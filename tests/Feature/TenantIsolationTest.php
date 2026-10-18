<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Models;
use Phoenix1331\FilamentHeadroom\Pages\Overview;
use Phoenix1331\FilamentHeadroom\Pages\RequestDetail;
use Phoenix1331\FilamentHeadroom\Pages\Requests;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\Team;
use Phoenix1331\FilamentHeadroom\Widgets\ModelsTable;
use Phoenix1331\FilamentHeadroom\Widgets\StatsOverview;

use function Pest\Livewire\livewire;

/*
 * Security: with tenant scoping on, the current tenant (Acme, 1) never sees
 * anything of another tenant (Globex, 2): not a figure, a name, a filter
 * option, a run step or a detail page. Each tenant uses its own agent, model,
 * provider and feature tag, so any leak shows up as one of those strings.
 */

uses(WithLedgerDatabase::class);

const ACME = ['agent' => 'App\\Ai\\Agents\\AcmeAgent', 'model' => 'gpt-5', 'feature' => 'acme-chat'];
const GLOBEX = ['agent' => 'App\\Ai\\Agents\\GlobexAgent', 'model' => 'claude-sonnet-5-5', 'feature' => 'globex-billing'];

/**
 * Strings that would only appear if Globex data leaked.
 *
 * @return list<string>
 */
function globexMarkers(): array
{
    return ['GlobexAgent', 'claude-sonnet-5-5', 'anthropic', 'globex-billing'];
}

/**
 * @param  array{agent: string, model: string, feature: string}  $tenant
 */
function tenantEntry(int $tenantId, array $tenant, int $minutesAgo = 5, ?callable $state = null, array $attributes = []): string
{
    $factory = LedgerEntryFactory::new()
        ->forModel($tenant['model'])
        ->forAgent($tenant['agent'])
        ->at(CarbonImmutable::now()->subMinutes($minutesAgo))
        ->withTags(['tenant_id' => $tenantId, 'feature' => $tenant['feature']]);

    return ($state === null ? $factory : $state($factory))->create($attributes)->request_id;
}

beforeEach(function (): void {
    config(['headroom-ledger.tags.allowed' => ['tenant_id', 'feature']]);

    HeadroomPlugin::get()->authorize(true)->scopeToTenant('tenant_id');
    Filament::setTenant(Team::make(1), isQuiet: true);

    $this->sharedRun = (string) Str::uuid();

    $this->acme = [
        tenantEntry(1, ACME, 10, attributes: ['actual_cost' => 1_110_000, 'invocation_id' => $this->sharedRun]),
        tenantEntry(1, ACME, 20, fn ($f) => $f->warned()),
        tenantEntry(1, ACME, 30, fn ($f) => $f->blocked()),
    ];

    // Globex: bigger, costlier and nearer the limit, so it would top every list if it leaked.
    $this->globex = [
        tenantEntry(2, GLOBEX, 9, attributes: ['actual_cost' => 9_990_000, 'invocation_id' => $this->sharedRun]),
        tenantEntry(2, GLOBEX, 8, fn ($f) => $f->warned(), ['estimated_input_tokens' => 190_000]),
        tenantEntry(2, GLOBEX, 7, fn ($f) => $f->failed()),
        tenantEntry(2, GLOBEX, 6, fn ($f) => $f->blocked()),
        tenantEntry(2, GLOBEX, 5, attributes: ['actual_cost' => 5_550_000]),
    ];
});

dataset('widgets', fn (): array => array_combine(
    array_map(class_basename(...), [...HeadroomPlugin::WIDGETS, ModelsTable::class]),
    array_map(fn (string $widget): array => [$widget], [...HeadroomPlugin::WIDGETS, ModelsTable::class]),
));

describe('widgets', function (): void {
    it('render nothing from another tenant', function (string $widget): void {
        $html = livewire($widget)->assertOk()->html();

        foreach (globexMarkers() as $marker) {
            expect($html)->not->toContain($marker);
        }
    })->with('widgets');

    it('render nothing at all without a tenant', function (string $widget): void {
        Filament::setTenant(null, isQuiet: true);

        $html = livewire($widget)->assertOk()->html();

        foreach ([...globexMarkers(), 'AcmeAgent', 'acme-chat'] as $marker) {
            expect($html)->not->toContain($marker);
        }
    })->with('widgets');

    it('count only the tenant in the stats', function (): void {
        livewire(StatsOverview::class)
            ->assertSeeInOrder(['Requests', '3', 'Average context used', 'Blocked', '1', 'Warned', '1'])
            ->assertDontSee('$9.99')
            ->assertDontSee('$5.55');
    });
});

describe('pages', function (): void {
    it('render nothing from another tenant', function (string $page): void {
        $html = livewire($page)->assertOk()->html();

        foreach (globexMarkers() as $marker) {
            expect($html)->not->toContain($marker);
        }
    })->with([Overview::class, Models::class, Requests::class]);

    it('list only the tenant in the Requests table', function (): void {
        livewire(Requests::class)
            ->assertCanSeeTableRecords($this->acme)
            ->assertCanNotSeeTableRecords($this->globex);
    });

    it('list only the tenant in every Requests sort', function (string $column): void {
        livewire(Requests::class)
            ->sortTable($column, 'desc')
            ->assertCanSeeTableRecords($this->acme)
            ->assertCanNotSeeTableRecords($this->globex);
    })->with(['counted_at', 'input_tokens', 'output_tokens', 'percent_used', 'cost']);

    it('offer only the tenant in every filter option', function (): void {
        $filters = livewire(Requests::class)->instance()->getTable()->getFilters();

        foreach ($filters as $name => $filter) {
            if (! method_exists($filter, 'getOptions')) {
                continue;
            }

            $options = json_encode($filter->getOptions());

            foreach (globexMarkers() as $marker) {
                expect($options)->not->toContain($marker, "Filter [{$name}] offers [{$marker}]");
            }
        }

        expect($filters)->not->toHaveKey('tag_tenant_id');
    });

    it('show only the tenant steps of a run shared with another tenant', function (): void {
        $runs = Livewire::withQueryParams(['group' => 'run'])->test(Requests::class);
        $rows = $runs->instance()->getTableRecords()->getCollection();

        expect($rows[$this->sharedRun]['steps'])->toBe(1)
            ->and($rows[$this->sharedRun]['agent'])->toBe(ACME['agent']);

        $html = $runs->html();

        foreach (globexMarkers() as $marker) {
            expect($html)->not->toContain($marker);
        }
    });

    it('show only the tenant rows on the Models page', function (): void {
        $widget = livewire(ModelsTable::class)->instance();
        $rows = (fn (): array => $this->rows('requests', 'desc'))->call($widget);

        expect(array_keys($rows))->toBe([ACME['model']])
            ->and($rows[ACME['model']]['requests'])->toBe(3);
    });
});

describe('detail view', function (): void {
    it('opens the tenant own request', function (): void {
        livewire(RequestDetail::class, ['requestId' => $this->acme[0]])->assertOk()->assertSee('AcmeAgent');
    });

    it('returns 404 for every request of another tenant', function (): void {
        foreach ($this->globex as $id) {
            livewire(RequestDetail::class, ['requestId' => $id])->assertNotFound();
        }
    });

    it('leaves other tenant steps out of a shared run', function (): void {
        $html = livewire(RequestDetail::class, ['requestId' => $this->acme[0]])->assertSee('1 step by AcmeAgent')->html();

        foreach (globexMarkers() as $marker) {
            expect($html)->not->toContain($marker);
        }
    });

    it('returns 404 for everything without a tenant', function (): void {
        Filament::setTenant(null, isQuiet: true);

        livewire(RequestDetail::class, ['requestId' => $this->acme[0]])->assertNotFound();
    });

    it('follows a tenant switch', function (): void {
        Filament::setTenant(Team::make(2), isQuiet: true);

        livewire(RequestDetail::class, ['requestId' => $this->globex[0]])->assertOk()->assertSee('GlobexAgent');
        livewire(RequestDetail::class, ['requestId' => $this->acme[0]])->assertNotFound();
    });
});
