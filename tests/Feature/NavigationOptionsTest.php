<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\NavigablePage;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\OtherNavigablePage;

enum NavigationGroup: string
{
    case Monitoring = 'monitoring';
}

describe('navigation group', function (): void {
    it('defaults to the translated Headroom group', function (): void {
        expect(NavigablePage::getNavigationGroup())->toBe('Headroom');

        app('translator')->addLines(['navigation.group' => 'Marge'], 'fr', 'filament-headroom');
        app()->setLocale('fr');

        expect(NavigablePage::getNavigationGroup())->toBe('Marge');
    });

    it('uses a custom group', function (mixed $group, mixed $expected): void {
        HeadroomPlugin::get()->navigationGroup($group);

        expect(NavigablePage::getNavigationGroup())->toBe($expected);
    })->with([
        'string' => ['AI', 'AI'],
        'enum' => [NavigationGroup::Monitoring, NavigationGroup::Monitoring],
        'closure' => [fn (): string => 'Observability', 'Observability'],
        'ungrouped' => [null, null],
    ]);

    it('rejects a closure that resolves to something else', function (): void {
        HeadroomPlugin::get()->navigationGroup(fn (): int => 42);

        NavigablePage::getNavigationGroup();
    })->throws(InvalidArgumentException::class, 'must resolve to a string, an enum or null, int given');
});

describe('navigation icon', function (): void {
    it('keeps each page icon by default', function (): void {
        expect(NavigablePage::getNavigationIcon())->toBe('heroicon-o-chart-bar')
            ->and(OtherNavigablePage::getNavigationIcon())->toBe('heroicon-o-table-cells');
    });

    it('sets the icon for every page', function (): void {
        HeadroomPlugin::get()->navigationIcon(Heroicon::OutlinedCpuChip);

        expect(NavigablePage::getNavigationIcon())->toBe(Heroicon::OutlinedCpuChip)
            ->and(OtherNavigablePage::getNavigationIcon())->toBe(Heroicon::OutlinedCpuChip);
    });

    it('sets the icon for one page, ahead of the shared icon', function (): void {
        HeadroomPlugin::get()
            ->navigationIcon('heroicon-o-bolt')
            ->navigationIcon(fn (): HtmlString => new HtmlString('<svg></svg>'), NavigablePage::class);

        expect(NavigablePage::getNavigationIcon())->toBeInstanceOf(HtmlString::class)
            ->and(OtherNavigablePage::getNavigationIcon())->toBe('heroicon-o-bolt');
    });

    it('rejects a closure that resolves to something else', function (): void {
        HeadroomPlugin::get()->navigationIcon(fn (): array => []);

        NavigablePage::getNavigationIcon();
    })->throws(InvalidArgumentException::class, 'must resolve to a string, an enum or Htmlable, array given');
});

describe('navigation sort', function (): void {
    it('keeps each page sort by default', function (): void {
        expect(NavigablePage::getNavigationSort())->toBe(2)
            ->and(OtherNavigablePage::getNavigationSort())->toBeNull();
    });

    it('offsets the block of pages from the base sort', function (int|Closure $sort): void {
        HeadroomPlugin::get()->navigationSort($sort);

        expect(NavigablePage::getNavigationSort())->toBe(12)
            ->and(OtherNavigablePage::getNavigationSort())->toBe(10);
    })->with([
        'integer' => [10],
        'closure' => [fn (): int => 10],
    ]);

    it('rejects a closure that resolves to something else', function (): void {
        HeadroomPlugin::get()->navigationSort(fn (): string => 'first');

        NavigablePage::getNavigationSort();
    })->throws(InvalidArgumentException::class, 'must resolve to an integer or null, string given');
});

it('falls back to page defaults on panels without the plugin', function (): void {
    HeadroomPlugin::get()->navigationGroup('AI')->navigationIcon('heroicon-o-bolt')->navigationSort(10);

    Filament::setCurrentPanel(Panel::make()->id('host'));

    expect(NavigablePage::getNavigationGroup())->toBeNull()
        ->and(NavigablePage::getNavigationIcon())->toBe('heroicon-o-chart-bar')
        ->and(NavigablePage::getNavigationSort())->toBe(2);
});
