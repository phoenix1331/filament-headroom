<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Phoenix1331\AiHeadroomLedger\Data\Money;
use Phoenix1331\AiHeadroomLedger\Data\Spend;
use Phoenix1331\AiHeadroomLedger\Data\SpendSummary;
use Phoenix1331\FilamentHeadroom\Enums\ValueBasis;
use Phoenix1331\FilamentHeadroom\Support\Cost;

function usd(int $microUnits, bool $estimated = false, bool $approximate = false): Money
{
    return new Money($microUnits, 'USD', $estimated, $approximate);
}

describe('formatting', function (): void {
    it('formats whole-cent amounts to two decimals', function (): void {
        expect(Cost::fromMoney(usd(1_234_567))->amount())->toBe('$1.23')
            ->and(Cost::fromMoney(usd(0))->amount())->toBe('$0.00');
    });

    it('keeps sub-cent amounts visible', function (): void {
        expect(Cost::fromMoney(usd(2_500))->amount())->toBe('$0.0025')
            ->and(Cost::fromMoney(usd(9_999))->amount())->toBe('$0.0100')
            ->and(Cost::fromMoney(usd(10_000))->amount())->toBe('$0.01');
    });

    it('keeps currencies apart instead of converting them', function (): void {
        $spend = new Spend([usd(1_500_000), new Money(500_000, 'EUR'), usd(500_000)]);

        expect(Cost::fromSpend($spend)->amount())->toBe('€0.50 + $2.00');
    });

    it('formats for the app locale', function (): void {
        app()->setLocale('de');

        // ICU separates the symbol with a no-break space whose exact code point varies by version.
        $amount = preg_replace('/\s/u', ' ', Cost::fromMoney(new Money(1_234_560_000, 'EUR'))->amount());

        expect($amount)->toBe('1.234,56 €');
    });

    it('says when there is no cost', function (): void {
        expect(Cost::fromMoney(null)->amount())->toBe('No cost recorded')
            ->and(Cost::fromSpend(new Spend)->isEmpty())->toBeTrue()
            ->and(Cost::fromSpend(new Spend)->label())->toBe('No cost recorded');
    });
});

describe('basis', function (): void {
    it('reads the basis from the money flags', function (Money $money, ValueBasis $basis): void {
        expect(Cost::fromMoney($money)->basis)->toBe($basis);
    })->with([
        'reported' => [usd(100_000), ValueBasis::Reported],
        'estimated' => [usd(100_000, estimated: true), ValueBasis::Estimated],
        'approximate' => [usd(100_000, approximate: true), ValueBasis::Approximate],
        'estimate outranks approximate' => [usd(100_000, estimated: true, approximate: true), ValueBasis::Estimated],
    ]);

    it('flags a multi-currency spend when any currency is estimated', function (): void {
        $spend = new Spend([new Money(100_000, 'EUR', approximate: true), usd(100_000, estimated: true)]);

        expect(Cost::fromSpend($spend)->basis)->toBe(ValueBasis::Estimated);
    });

    it('tells a wholly estimated summary from one that includes estimates', function (): void {
        $estimateOnly = new SpendSummary(
            total: new Spend([usd(300_000, estimated: true)]),
            estimatedOnly: new Spend([usd(300_000, estimated: true)]),
            requests: 2,
            requestsWithUsage: 0,
            estimateOnlyRequests: 2,
        );
        $mixed = new SpendSummary(
            total: new Spend([usd(900_000, estimated: true)]),
            actual: new Spend([usd(600_000)]),
            estimatedOnly: new Spend([usd(300_000, estimated: true)]),
            requests: 3,
            requestsWithUsage: 1,
            estimateOnlyRequests: 2,
        );
        $reported = new SpendSummary(
            total: new Spend([usd(600_000, approximate: true)]),
            actual: new Spend([usd(600_000, approximate: true)]),
            requests: 1,
            requestsWithUsage: 1,
        );

        expect(Cost::fromSummary($estimateOnly)->basis)->toBe(ValueBasis::Estimated)
            ->and(Cost::fromSummary($mixed)->basis)->toBe(ValueBasis::IncludesEstimates)
            ->and(Cost::fromSummary($reported)->basis)->toBe(ValueBasis::Approximate)
            ->and(Cost::fromSummary(new SpendSummary)->isEmpty())->toBeTrue();
    });

    it('keeps an empty estimated summary unbadged', function (): void {
        $summary = new SpendSummary(requests: 1, estimateOnlyRequests: 1);

        expect(Cost::fromSummary($summary)->basis)->toBe(ValueBasis::Reported)
            ->and(Cost::fromSummary($summary)->label())->toBe('No cost recorded');
    });

    it('labels, explains and colours every basis that is not reported', function (ValueBasis $basis, string $label, string $color): void {
        expect($basis->getLabel())->toBe($label)
            ->and($basis->getTooltip())->not->toBeEmpty()
            ->and($basis->getColor())->toBe($color)
            ->and($basis->isReported())->toBeFalse();
    })->with([
        [ValueBasis::Estimated, 'Estimate', 'warning'],
        [ValueBasis::IncludesEstimates, 'Includes estimates', 'warning'],
        [ValueBasis::Approximate, 'Approximate', 'gray'],
    ]);

    it('gives reported values no badge', function (): void {
        expect(ValueBasis::Reported->getLabel())->toBeNull()
            ->and(ValueBasis::Reported->getTooltip())->toBeNull()
            ->and(ValueBasis::Reported->getColor())->toBeNull()
            ->and(ValueBasis::Reported->isReported())->toBeTrue();
    });
});

describe('plain-text label', function (): void {
    it('adds the basis in words', function (): void {
        expect(Cost::fromMoney(usd(1_240_000, estimated: true))->label())->toBe('$1.24 (estimate)')
            ->and(Cost::fromMoney(usd(1_240_000, approximate: true))->label())->toBe('$1.24 (approximate)')
            ->and(Cost::fromMoney(usd(1_240_000))->label())->toBe('$1.24');
    });
});

describe('badge component', function (): void {
    it('shows a badge with an explanation for estimates', function (): void {
        $html = Blade::render('<x-filament-headroom::cost :cost="$cost" />', ['cost' => Cost::fromMoney(usd(1_240_000, estimated: true))]);

        expect($html)->toContain('$1.24')
            ->toContain('Estimate')
            ->toContain('fi-badge')
            ->toContain('pre-flight estimate');
    });

    it('shows reported cost without a badge', function (): void {
        $html = Blade::render('<x-filament-headroom::cost :cost="$cost" class="extra" />', ['cost' => Cost::fromMoney(usd(1_240_000))]);

        expect($html)->toContain('$1.24')
            ->toContain('extra')
            ->not->toContain('fi-badge');
    });

    it('shows no badge when there is no cost', function (): void {
        $html = Blade::render('<x-filament-headroom::cost :cost="$cost" />', ['cost' => Cost::fromMoney(null)]);

        expect($html)->toContain('No cost recorded')->not->toContain('fi-badge');
    });
});
