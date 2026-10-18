<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\Overview;
use Phoenix1331\FilamentHeadroom\Pages\RequestDetail;
use Phoenix1331\FilamentHeadroom\Pages\Requests;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

describe('registration', function (): void {
    it('is registered with the Requests page, out of the navigation', function (): void {
        expect(Route::has(RequestDetail::getRouteName()))->toBeTrue()
            ->and(RequestDetail::getUrl(['requestId' => 'abc123']))->toEndWith('/admin/headroom/requests/abc123')
            ->and(RequestDetail::shouldRegisterNavigation())->toBeFalse()
            ->and(HeadroomPlugin::PAGES)->not->toContain(RequestDetail::class);
    });

    it('is not registered when the Requests page is disabled', function (): void {
        $panel = Panel::make()->id('parts')->plugin(HeadroomPlugin::make()->pages([Overview::class]));

        expect($panel->getPages())->toBe([Overview::class]);
    });

    it('follows a Requests subclass', function (): void {
        $subclass = new class extends Requests {};
        $panel = Panel::make()->id('parts')->plugin(HeadroomPlugin::make()->pages([$subclass::class]));

        expect($panel->getPages())->toBe([$subclass::class, RequestDetail::class]);
    });
});

describe('access', function (): void {
    it('refuses access by default outside local', function (): void {
        HeadroomPlugin::get()->authorize(false);
        $id = LedgerEntryFactory::new()->create()->request_id;

        livewire(RequestDetail::class, ['requestId' => $id])->assertForbidden();
    });

    it('returns 404 for an unknown request', function (): void {
        livewire(RequestDetail::class, ['requestId' => 'missing'])->assertNotFound();
    });
});

describe('details', function (): void {
    it('shows a completed request with estimate against actual', function (): void {
        config(['headroom-ledger.tags.allowed' => ['tenant_id']]);
        HeadroomPlugin::get()->tagLabel(fn (string $key, string $value): string => "Tenant #{$value}");

        $entry = LedgerEntryFactory::new()
            ->forModel('gpt-5')
            ->countMethod(CountMethod::Calibrated)
            ->withTags(['tenant_id' => 42])
            ->create([
                'agent' => 'App\\Ai\\Agents\\SupportAgent',
                'estimated_input_tokens' => 9_000,
                'actual_input_tokens' => 10_000,
                'output_tokens' => 512,
                'actual_cost' => 1_240_000,
                'estimated_cost' => 2_000_000,
            ]);

        livewire(RequestDetail::class, ['requestId' => $entry->request_id])
            ->assertOk()
            ->assertSee('Request '.mb_substr($entry->request_id, -8))
            ->assertSee('Completed')
            ->assertSee('App\\Ai\\Agents\\SupportAgent')
            ->assertSee('gpt-5 (openai)')
            ->assertSee('Calibrated')
            ->assertSee('9,000')
            ->assertSee('10,000')
            ->assertSee('+1,000 tokens (10%)')
            ->assertSee('512')
            ->assertSee('$1.24')
            ->assertSee('$2.00')
            ->assertSee("Headroom's worst case")
            ->assertSee('Tenant #42')
            ->assertSee('Instructions')
            ->assertSee('History');
    });

    it('labels a blocked request as estimate only', function (): void {
        $entry = LedgerEntryFactory::new()->blocked()->create();

        livewire(RequestDetail::class, ['requestId' => $entry->request_id])
            ->assertSee('Blocked')
            ->assertSee('Did not fit the context window')
            ->assertSee('Not reported')
            ->assertSee('Estimate');
    });

    it('shows failover between models', function (): void {
        $entry = LedgerEntryFactory::new()->forModel('claude-sonnet-5-5')->failover('gpt-5')->create();

        livewire(RequestDetail::class, ['requestId' => $entry->request_id])
            ->assertSee('claude-sonnet-5-5, answered by gpt-5 (failover)');
    });

    it('shows every step of the run with context growth', function (): void {
        $run = (string) Str::uuid();
        $ids = [];

        foreach ([1_000, 4_000, 3_500] as $index => $estimate) {
            $ids[] = LedgerEntryFactory::new()
                ->at(CarbonImmutable::now()->subMinutes(10 - $index))
                ->create(['invocation_id' => $run, 'agent' => 'App\\Ai\\Agents\\ResearchAgent', 'estimated_input_tokens' => $estimate])
                ->request_id;
        }

        $steps = RequestDetail::steps(HeadroomPlugin::ledger()->run($run), $ids[1]);

        expect(array_column($steps, 'estimated'))->toBe(['1,000', '4,000', '3,500'])
            ->and(array_column($steps, 'growth'))->toBe([null, '+3,000', '-500'])
            ->and(array_column($steps, 'current'))->toBe([false, true, false])
            ->and($steps[0]['url'])->toBe(RequestDetail::getUrl(['requestId' => $ids[0]]));

        livewire(RequestDetail::class, ['requestId' => $ids[1]])
            ->assertSee('Agent run')
            ->assertSee('3 steps by ResearchAgent')
            ->assertSeeInOrder(['1,000', '4,000', '+3,000', '3,500', '-500'])
            ->assertSeeHtml('aria-current="true"');
    });

    it('leaves out the run section for a lone request', function (): void {
        $entry = LedgerEntryFactory::new()->create(['invocation_id' => null]);

        livewire(RequestDetail::class, ['requestId' => $entry->request_id])->assertDontSee('Agent run');
    });
});

describe('links from the Requests page', function (): void {
    it('opens a request from its row', function (): void {
        $id = LedgerEntryFactory::new()->at(CarbonImmutable::now()->subMinutes(5))->create()->request_id;

        livewire(Requests::class)->assertSeeHtml(e(RequestDetail::getUrl(['requestId' => $id])));
    });

    it('opens a run from its first step', function (): void {
        $run = (string) Str::uuid();
        $first = LedgerEntryFactory::new()->at(CarbonImmutable::now()->subMinutes(5))->create(['invocation_id' => $run])->request_id;
        LedgerEntryFactory::new()->at(CarbonImmutable::now()->subMinutes(4))->create(['invocation_id' => $run]);

        Livewire::withQueryParams(['group' => 'run'])->test(Requests::class)
            ->assertSeeHtml(e(RequestDetail::getUrl(['requestId' => $first])));
    });
});
