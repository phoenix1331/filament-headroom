<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;
use Phoenix1331\FilamentHeadroom\Widgets\TokensByAgent;

use function Pest\Livewire\livewire;

uses(WithLedgerDatabase::class);

beforeEach(function (): void {
    HeadroomPlugin::get()->authorize(true);
});

function agentUsage(?string $agent, int $input, int $output): void
{
    LedgerEntryFactory::new()
        ->at(CarbonImmutable::now()->setTime(12, 0))
        ->create(['agent' => $agent, 'actual_input_tokens' => $input, 'output_tokens' => $output]);
}

it('is one of the built-in widgets', function (): void {
    expect(HeadroomPlugin::WIDGETS)->toContain(TokensByAgent::class);
});

it('refuses to mount for users the plugin does not authorise', function (): void {
    HeadroomPlugin::get()->authorize(false);

    livewire(TokensByAgent::class)->assertForbidden();
});

it('draws nothing for an empty ledger', function (): void {
    $component = livewire(TokensByAgent::class)->assertOk()->assertDontSee('never reported usage');

    expect(chartData($component))->toBe(['labels' => [], 'datasets' => []]);
});

it('splits reported input and output per agent, busiest first', function (): void {
    agentUsage('App\\Ai\\Agents\\SupportAgent', 1_000, 200);
    agentUsage('App\\Ai\\Agents\\SupportAgent', 500, 100);
    agentUsage('App\\Ai\\Agents\\ResearchAgent', 9_000, 1_000);
    agentUsage(null, 50, 5);

    $data = chartData(livewire(TokensByAgent::class));

    expect($data['labels'])->toBe(['ResearchAgent', 'SupportAgent', 'No agent'])
        ->and($data['datasets'][0])->toMatchArray(['label' => 'Input', 'data' => [9_000, 1_500, 50], 'slot' => 0])
        ->and($data['datasets'][1])->toMatchArray(['label' => 'Output', 'data' => [1_000, 300, 5], 'slot' => 1]);
});

it('never draws requests that did not report usage, and says how many', function (int $count, string $message): void {
    agentUsage('App\\Ai\\Agents\\SupportAgent', 1_000, 200);
    LedgerEntryFactory::new()->blocked()->forAgent('App\\Ai\\Agents\\SupportAgent')->count($count)->at(CarbonImmutable::now()->setTime(12, 0))->create();

    $component = livewire(TokensByAgent::class)->assertSee($message);

    expect(chartData($component)['datasets'][0]['data'])->toBe([1_000]);
})->with([
    'one' => [1, 'Reported usage only: 1 request never reported usage and is not included.'],
    'several' => [3, 'Reported usage only: 3 requests never reported usage and are not included.'],
]);

it('leaves out agents with no reported usage', function (): void {
    agentUsage('App\\Ai\\Agents\\SupportAgent', 1_000, 200);
    LedgerEntryFactory::new()->counted()->forAgent('App\\Ai\\Agents\\SummaryAgent')->at(CarbonImmutable::now()->setTime(12, 0))->create();

    expect(chartData(livewire(TokensByAgent::class))['labels'])->toBe(['SupportAgent']);
});

it('shows the full name when two agents share a basename', function (): void {
    agentUsage('App\\Ai\\Agents\\SupportAgent', 1_000, 200);
    agentUsage('App\\Billing\\SupportAgent', 500, 100);

    expect(chartData(livewire(TokensByAgent::class))['labels'])->toBe(['App\\Ai\\Agents\\SupportAgent', 'App\\Billing\\SupportAgent']);
});

it('folds agents beyond the limit into one bar', function (): void {
    foreach (range(1, TokensByAgent::MAX_AGENTS + 2) as $i) {
        agentUsage("App\\Ai\\Agents\\Agent{$i}", $i * 100, $i * 10);
    }

    $data = chartData(livewire(TokensByAgent::class));

    expect($data['labels'])->toHaveCount(TokensByAgent::MAX_AGENTS + 1)
        ->and($data['labels'][TokensByAgent::MAX_AGENTS])->toBe('Other agents')
        ->and($data['datasets'][0]['data'][TokensByAgent::MAX_AGENTS])->toBe(300)
        ->and($data['datasets'][1]['data'][TokensByAgent::MAX_AGENTS])->toBe(30);
});

it('follows the page range', function (): void {
    agentUsage('App\\Ai\\Agents\\SupportAgent', 1_000, 200);
    LedgerEntryFactory::new()->forAgent('App\\Ai\\Agents\\ResearchAgent')->at(CarbonImmutable::now()->subDays(10))->create(['actual_input_tokens' => 5_000, 'output_tokens' => 1]);

    expect(chartData(livewire(TokensByAgent::class, ['pageFilters' => ['range' => '7d']]))['labels'])->toBe(['SupportAgent'])
        ->and(chartData(livewire(TokensByAgent::class, ['pageFilters' => ['range' => '30d']]))['labels'])->toBe(['ResearchAgent', 'SupportAgent']);
});

it('builds attribute-safe options with the validated palette', function (): void {
    $options = (fn () => $this->getOptions())->call(livewire(TokensByAgent::class)->instance())->toHtml();

    expect($options)->toContain("indexAxis: 'y'")->not->toContain('"');
});
