<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Panel;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Phoenix1331\AiHeadroomLedger\Facades\Ledger;
use Phoenix1331\FilamentHeadroom\Enums\Range;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Tests\Concerns\WithLedgerDatabase;

uses(WithLedgerDatabase::class);

describe('ranges', function (): void {
    it('covers today and the days before it', function (Range $range, int $days, string $first): void {
        [$from, $to] = $range->between(CarbonImmutable::parse('2026-10-18 15:30'));

        expect($range->days())->toBe($days)
            ->and($from->toDateString())->toBe($first)
            ->and($to->toDateString())->toBe('2026-10-18')
            ->and($from->toTimeString())->toBe('00:00:00');
    })->with([
        [Range::SevenDays, 7, '2026-10-12'],
        [Range::ThirtyDays, 30, '2026-09-19'],
        [Range::NinetyDays, 90, '2026-07-21'],
    ]);

    it('labels every range for selects', function (): void {
        expect(Range::options())->toBe([
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '90d' => 'Last 90 days',
        ]);
    });

    it('narrows a ledger query to the range', function (): void {
        $now = CarbonImmutable::now();

        foreach ([0, 6, 7, 29, 30, 89, 90] as $daysAgo) {
            LedgerEntryFactory::new()->at($now->subDays($daysAgo)->setTime(12, 0))->create();
        }

        expect(Range::SevenDays->apply(Ledger::query())->count())->toBe(2)
            ->and(Range::ThirtyDays->apply(Ledger::query())->count())->toBe(4)
            ->and(Range::NinetyDays->apply(Ledger::query())->count())->toBe(6);
    });

    it('resolves page and filter state', function (mixed $state, Range $expected): void {
        expect(Range::resolve($state))->toBe($expected);
    })->with([
        'enum' => [Range::NinetyDays, Range::NinetyDays],
        'value' => ['7d', Range::SevenDays],
        'unknown value' => ['14d', Range::ThirtyDays],
        'null' => [null, Range::ThirtyDays],
        'other type' => [7, Range::ThirtyDays],
    ]);
});

describe('default range', function (): void {
    it('defaults to 30 days', function (): void {
        expect(HeadroomPlugin::get()->getDefaultRange())->toBe(Range::ThirtyDays);
    });

    it('accepts a value, an enum or a closure', function (mixed $range, Range $expected): void {
        HeadroomPlugin::get()->defaultRange($range);

        expect(HeadroomPlugin::get()->getDefaultRange())->toBe($expected)
            ->and(Range::resolve(null))->toBe($expected);
    })->with([
        'value' => ['7d', Range::SevenDays],
        'enum' => [Range::NinetyDays, Range::NinetyDays],
        'closure' => [fn (): string => '90d', Range::NinetyDays],
    ]);

    it('rejects an unsupported range as soon as it is set', function (): void {
        HeadroomPlugin::get()->defaultRange('14d');
    })->throws(InvalidArgumentException::class, "must be one of '7d', '30d', '90d', '14d' given");

    it('rejects a closure that resolves to an unsupported range', function (): void {
        HeadroomPlugin::get()->defaultRange(fn (): int => 7)->getDefaultRange();
    })->throws(InvalidArgumentException::class, "must be one of '7d', '30d', '90d', int given");

    it('uses the built-in default on panels without the plugin', function (): void {
        HeadroomPlugin::get()->defaultRange('7d');
        Filament::setCurrentPanel(Panel::make()->id('host'));

        expect(Range::resolve(null))->toBe(Range::ThirtyDays);
    });
});

describe('tag label', function (): void {
    it('shows key and value by default', function (): void {
        expect(HeadroomPlugin::get()->getTagLabel('tenant_id', '42'))->toBe('tenant_id: 42');
    });

    it('uses a custom label with key and value injected', function (): void {
        $tenants = ['42' => 'Acme Ltd'];

        HeadroomPlugin::get()->tagLabel(
            fn (string $key, string $value): string => $key === 'tenant_id' ? ($tenants[$value] ?? $value) : "{$key}={$value}",
        );

        expect(HeadroomPlugin::get()->getTagLabel('tenant_id', '42'))->toBe('Acme Ltd')
            ->and(HeadroomPlugin::get()->getTagLabel('feature', 'chat'))->toBe('feature=chat');
    });

    it('restores the default label', function (): void {
        HeadroomPlugin::get()->tagLabel(fn (): string => 'custom')->tagLabel(null);

        expect(HeadroomPlugin::get()->getTagLabel('feature', 'chat'))->toBe('feature: chat');
    });

    it('rejects a label that is not a string', function (): void {
        HeadroomPlugin::get()->tagLabel(fn (): int => 42)->getTagLabel('tenant_id', '42');
    })->throws(InvalidArgumentException::class, 'must resolve to a string, int given');
});
