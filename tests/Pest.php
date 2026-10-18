<?php

declare(strict_types=1);

use Livewire\Features\SupportTesting\Testable;
use Phoenix1331\FilamentHeadroom\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

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
