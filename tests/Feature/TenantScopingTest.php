<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Panel;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Requests;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\Team;
use Phoenix1331\FilamentHeadroom\Widgets\StatsOverview;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

/**
 * @param  array<string, scalar>  $tags
 */
function tagged(array $tags, int $count = 1): void
{
    LedgerEntryFactory::new()->count($count)->at(CarbonImmutable::now()->subMinutes(5))->withTags($tags)->create();
}

beforeEach(function (): void {
    config(['headroom-ledger.tags.allowed' => ['tenant_id', 'tenant_slug', 'feature']]);

    tagged(['tenant_id' => 1, 'tenant_slug' => 'acme', 'feature' => 'chat'], 3);
    tagged(['tenant_id' => 2, 'tenant_slug' => 'globex', 'feature' => 'chat'], 2);
    tagged(['feature' => 'chat']);
});

it('reads every tenant when scoping is off', function (): void {
    Filament::setTenant(Team::make(1), isQuiet: true);

    expect(HeadroomPlugin::get()->getTenantTag())->toBeNull()
        ->and(HeadroomPlugin::ledger()->count())->toBe(6);
});

it('scopes every ledger read to the current tenant by its key', function (): void {
    HeadroomPlugin::get()->scopeToTenant('tenant_id');

    Filament::setTenant(Team::make(1), isQuiet: true);
    expect(HeadroomPlugin::ledger()->count())->toBe(3);

    Filament::setTenant(Team::make(2), isQuiet: true);
    expect(HeadroomPlugin::ledger()->count())->toBe(2);
});

it('maps the tenant to the tag value the app records', function (): void {
    HeadroomPlugin::get()->scopeToTenant('tenant_slug', fn (Team $tenant): string => $tenant->slug);

    Filament::setTenant(Team::make(99, 'globex'), isQuiet: true);

    expect(HeadroomPlugin::get()->getTenantValue())->toBe('globex')
        ->and(HeadroomPlugin::ledger()->count())->toBe(2);
});

it('matches nothing when scoping is on but there is no tenant', function (): void {
    HeadroomPlugin::get()->scopeToTenant('tenant_id');
    Filament::setTenant(null, isQuiet: true);

    expect(HeadroomPlugin::get()->getTenantValue())->toBe(HeadroomPlugin::NO_TENANT)
        ->and(HeadroomPlugin::ledger()->count())->toBe(0);
});

it('can be turned off again', function (): void {
    HeadroomPlugin::get()->scopeToTenant('tenant_id')->scopeToTenant(null);
    Filament::setTenant(Team::make(1), isQuiet: true);

    expect(HeadroomPlugin::ledger()->count())->toBe(6);
});

it('rejects a tenant value that is not a scalar', function (): void {
    HeadroomPlugin::get()->scopeToTenant('tenant_id', fn (): array => [1]);
    Filament::setTenant(Team::make(1), isQuiet: true);

    HeadroomPlugin::ledger();
})->throws(InvalidArgumentException::class, 'must resolve to a scalar, array given');

it('leaves panels without the plugin unscoped', function (): void {
    HeadroomPlugin::get()->scopeToTenant('tenant_id');
    Filament::setCurrentPanel(Panel::make()->id('host'));

    expect(HeadroomPlugin::ledger()->count())->toBe(6);
});

it('applies to widgets', function (): void {
    HeadroomPlugin::get()->authorize(true)->scopeToTenant('tenant_id');
    Filament::setTenant(Team::make(2), isQuiet: true);

    livewire(StatsOverview::class)->assertSeeInOrder(['Requests', '2']);
});

it('drops the tenant tag from the Requests filters', function (): void {
    HeadroomPlugin::get()->authorize(true)->scopeToTenant('tenant_id');
    Filament::setTenant(Team::make(1), isQuiet: true);

    $filters = array_keys(livewire(Requests::class)->instance()->getTable()->getFilters());

    expect($filters)->not->toContain('tag_tenant_id')
        ->toContain('tag_tenant_slug', 'tag_feature');
});
