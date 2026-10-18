<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Pages\RequestDetail;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\AuthorisedPage;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\AuthorisedWidget;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\NavigablePage;

function panelWith(HeadroomPlugin $plugin): Panel
{
    return Panel::make()->id('parts')->plugin($plugin);
}

describe('pages', function (): void {
    it('registers the built-in pages by default', function (): void {
        $plugin = HeadroomPlugin::make();

        expect($plugin->getPages())->toBe(HeadroomPlugin::PAGES)
            ->and(panelWith($plugin)->getPages())->toBe([...HeadroomPlugin::PAGES, RequestDetail::class]);
    });

    it('registers only the enabled pages', function (): void {
        $panel = panelWith(HeadroomPlugin::make()->pages([AuthorisedPage::class, NavigablePage::class, AuthorisedPage::class]));

        expect($panel->getPages())->toBe([AuthorisedPage::class, NavigablePage::class]);
    });

    it('registers no pages when every page is disabled', function (): void {
        expect(panelWith(HeadroomPlugin::make()->pages([]))->getPages())->toBe([]);
    });

    it('rejects anything that is not a page class', function (mixed $page, string $given): void {
        HeadroomPlugin::make()->pages([$page]);
    })->with([
        'widget' => [AuthorisedWidget::class, '['.AuthorisedWidget::class.']'],
        'unknown class' => ['App\\Missing', '[App\\Missing]'],
        'not a string' => [42, 'int'],
    ])->throws(InvalidArgumentException::class, 'Headroom pages must be Filament\\Pages\\Page classes');
});

describe('widgets', function (): void {
    it('enables the built-in widgets by default', function (): void {
        expect(HeadroomPlugin::make()->getWidgets())->toBe(HeadroomPlugin::WIDGETS);
    });

    it('enables only the listed widgets', function (): void {
        $plugin = HeadroomPlugin::make()->widgets([AuthorisedWidget::class]);

        expect($plugin->getWidgets())->toBe([AuthorisedWidget::class])
            ->and($plugin->hasWidget(AuthorisedWidget::class))->toBeTrue();
    });

    it('hides a disabled widget even when access is allowed', function (): void {
        HeadroomPlugin::get()->authorize(true);
        expect(AuthorisedWidget::canView())->toBeTrue();

        HeadroomPlugin::get()->widgets([]);
        expect(AuthorisedWidget::canView())->toBeFalse();
    });

    it('treats every widget as enabled on panels without the plugin', function (): void {
        HeadroomPlugin::get()->widgets([]);
        app()->detectEnvironment(fn (): string => 'local');
        Filament::setCurrentPanel(Panel::make()->id('host'));

        expect(AuthorisedWidget::canView())->toBeTrue();
    });

    it('never adds widgets to the panel dashboard', function (): void {
        $panel = panelWith(HeadroomPlugin::make()->widgets([AuthorisedWidget::class]));

        expect($panel->getWidgets())->not->toContain(AuthorisedWidget::class);
    });

    it('rejects anything that is not a widget class', function (): void {
        HeadroomPlugin::make()->widgets([AuthorisedPage::class]);
    })->throws(InvalidArgumentException::class, 'Headroom widgets must be Filament\\Widgets\\Widget classes, ['.AuthorisedPage::class.'] given.');
});
