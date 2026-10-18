<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Requests;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
    config(['headroom-ledger.tags.allowed' => ['tenant_id', 'feature']]);
});

function entry(?callable $state = null, int $minutesAgo = 5): string
{
    $factory = LedgerEntryFactory::new()->at(CarbonImmutable::now()->subMinutes($minutesAgo));

    return ($state === null ? $factory : $state($factory))->create()->request_id;
}

/**
 * @return array<string, string>
 */
function filterOptions(string $filter): array
{
    $page = livewire(Requests::class)->instance();

    return $page->getTable()->getFilter($filter)->getOptions();
}

it('filters by range, defaulting to the plugin range', function (): void {
    $recent = entry();
    $older = entry(minutesAgo: 60 * 24 * 10);

    livewire(Requests::class)
        ->assertTableFilterExists('range')
        ->assertCanSeeTableRecords([$recent, $older])
        ->filterTable('range', '7d')
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$older]);
});

it('filters by agent', function (): void {
    $support = entry(fn ($f) => $f->forAgent('App\\Ai\\Agents\\SupportAgent'));
    $research = entry(fn ($f) => $f->forAgent('App\\Ai\\Agents\\ResearchAgent'));

    livewire(Requests::class)
        ->filterTable('agent', 'App\\Ai\\Agents\\SupportAgent')
        ->assertCanSeeTableRecords([$support])
        ->assertCanNotSeeTableRecords([$research]);

    expect(filterOptions('agent'))->toBe([
        'App\\Ai\\Agents\\ResearchAgent' => 'ResearchAgent',
        'App\\Ai\\Agents\\SupportAgent' => 'SupportAgent',
    ]);
});

it('filters by provider', function (): void {
    $openai = entry(fn ($f) => $f->forModel('gpt-5'));
    $anthropic = entry(fn ($f) => $f->forModel('claude-haiku-5-5'));

    livewire(Requests::class)
        ->filterTable('provider', 'openai')
        ->assertCanSeeTableRecords([$openai])
        ->assertCanNotSeeTableRecords([$anthropic]);

    expect(filterOptions('provider'))->toBe(['anthropic' => 'anthropic', 'openai' => 'openai']);
});

it('filters by model', function (): void {
    $gpt = entry(fn ($f) => $f->forModel('gpt-5'));
    $gemini = entry(fn ($f) => $f->forModel('gemini-2.5-flash'));

    livewire(Requests::class)
        ->filterTable('model', 'gemini-2.5-flash')
        ->assertCanSeeTableRecords([$gemini])
        ->assertCanNotSeeTableRecords([$gpt]);
});

it('filters by one or more outcomes', function (): void {
    $completed = entry();
    $blocked = entry(fn ($f) => $f->blocked());
    $warned = entry(fn ($f) => $f->warned());
    $failed = entry(fn ($f) => $f->failed());

    livewire(Requests::class)
        ->filterTable('outcome', ['blocked', 'failed'])
        ->assertCanSeeTableRecords([$blocked, $failed])
        ->assertCanNotSeeTableRecords([$completed, $warned]);
});

it('filters by count method', function (): void {
    $native = entry(fn ($f) => $f->countMethod(CountMethod::Native));
    $estimated = entry(fn ($f) => $f->countMethod(CountMethod::Estimated));
    $calibrated = entry(fn ($f) => $f->countMethod(CountMethod::Calibrated));

    livewire(Requests::class)
        ->filterTable('count_method', ['estimated', 'calibrated'])
        ->assertCanSeeTableRecords([$estimated, $calibrated])
        ->assertCanNotSeeTableRecords([$native]);
});

it('offers a filter for each allowlisted tag, using tagLabel()', function (): void {
    $acme = entry(fn ($f) => $f->withTags(['tenant_id' => 42, 'feature' => 'chat']));
    $globex = entry(fn ($f) => $f->withTags(['tenant_id' => 7, 'feature' => 'search']));
    $untagged = entry();

    HeadroomPlugin::get()->tagLabel(fn (string $key, string $value): string => $key === 'tenant_id' ? "Tenant #{$value}" : $value);

    expect(filterOptions('tag_tenant_id'))->toBe(['7' => 'Tenant #7', '42' => 'Tenant #42'])
        ->and(filterOptions('tag_feature'))->toBe(['chat' => 'chat', 'search' => 'search']);

    livewire(Requests::class)
        ->filterTable('tag_tenant_id', '42')
        ->assertCanSeeTableRecords([$acme])
        ->assertCanNotSeeTableRecords([$globex, $untagged]);
});

it('offers no tag filters when the ledger records no tags', function (): void {
    config(['headroom-ledger.tags.allowed' => []]);

    $filters = livewire(Requests::class)->instance()->getTable()->getFilters();

    expect(array_keys($filters))->toBe(['range', 'agent', 'provider', 'model', 'outcome', 'count_method']);
});

it('combines filters', function (): void {
    $match = entry(fn ($f) => $f->forModel('gpt-5')->blocked()->withTags(['tenant_id' => 1]));
    $wrongTenant = entry(fn ($f) => $f->forModel('gpt-5')->blocked()->withTags(['tenant_id' => 2]));
    $wrongOutcome = entry(fn ($f) => $f->forModel('gpt-5')->withTags(['tenant_id' => 1]));

    livewire(Requests::class)
        ->filterTable('model', 'gpt-5')
        ->filterTable('outcome', ['blocked'])
        ->filterTable('tag_tenant_id', '1')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$wrongTenant, $wrongOutcome]);
});

it('keeps filters when paging and sorting', function (): void {
    $blocked = collect(range(1, 12))->map(fn (int $i): string => entry(fn ($f) => $f->blocked(), $i))->all();
    $completed = entry();

    livewire(Requests::class)
        ->set('tableRecordsPerPage', 10)
        ->filterTable('outcome', ['blocked'])
        ->call('gotoPage', 2)
        ->assertCanSeeTableRecords(array_slice($blocked, 10))
        ->assertCanNotSeeTableRecords([$completed]);
});

it('remembers filters for the session', function (): void {
    $blocked = entry(fn ($f) => $f->blocked());
    $completed = entry();

    livewire(Requests::class)->filterTable('outcome', ['blocked']);

    livewire(Requests::class)
        ->assertCanSeeTableRecords([$blocked])
        ->assertCanNotSeeTableRecords([$completed]);
});
