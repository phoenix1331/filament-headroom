<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\AuthorisedPage;
use Phoenix1331\FilamentHeadroom\Tests\Fixtures\AuthorisedWidget;

use function Pest\Livewire\livewire;

function useEnvironment(string $environment): void
{
    app()->detectEnvironment(fn (): string => $environment);
}

function demoUser(string $email = 'admin@example.com'): GenericUser
{
    return new GenericUser(['id' => 1, 'email' => $email]);
}

describe('default rule', function (): void {
    it('denies access outside the local environment', function (string $environment): void {
        useEnvironment($environment);

        expect(HeadroomPlugin::get()->isAuthorized())->toBeFalse()
            ->and(AuthorisedPage::canAccess())->toBeFalse()
            ->and(AuthorisedWidget::canView())->toBeFalse();
    })->with(['production', 'staging', 'testing']);

    it('allows access in the local environment', function (): void {
        useEnvironment('local');

        expect(HeadroomPlugin::get()->isAuthorized())->toBeTrue()
            ->and(AuthorisedPage::canAccess())->toBeTrue()
            ->and(AuthorisedWidget::canView())->toBeTrue();
    });

    it('returns 403 when mounting a page', function (): void {
        $this->actingAs(demoUser());

        livewire(AuthorisedPage::class)->assertForbidden();
    });
});

describe('custom callback', function (): void {
    it('passes the signed-in user to the callback', function (): void {
        HeadroomPlugin::get()->authorize(
            fn (?Authenticatable $user): bool => $user instanceof GenericUser && $user->email === 'admin@example.com',
        );

        $this->actingAs(demoUser('admin@example.com'));
        expect(AuthorisedPage::canAccess())->toBeTrue();

        $this->actingAs(demoUser('guest@example.com'));
        expect(AuthorisedPage::canAccess())->toBeFalse();
    });

    it('injects the user by name', function (): void {
        HeadroomPlugin::get()->authorize(fn ($user): bool => $user !== null);

        expect(AuthorisedWidget::canView())->toBeFalse();

        $this->actingAs(demoUser());
        expect(AuthorisedWidget::canView())->toBeTrue();
    });

    it('checks an explicitly given user', function (): void {
        HeadroomPlugin::get()->authorize(fn (Authenticatable $user): bool => $user->getAuthIdentifier() === 7);

        expect(HeadroomPlugin::get()->isAuthorized(new GenericUser(['id' => 7])))->toBeTrue()
            ->and(HeadroomPlugin::get()->isAuthorized(new GenericUser(['id' => 8])))->toBeFalse();
    });

    it('overrides the default rule in both directions', function (): void {
        useEnvironment('local');
        HeadroomPlugin::get()->authorize(false);
        expect(AuthorisedPage::canAccess())->toBeFalse();

        useEnvironment('production');
        HeadroomPlugin::get()->authorize(true);
        expect(AuthorisedPage::canAccess())->toBeTrue();
    });

    it('denies when the callback returns anything other than true', function (mixed $result): void {
        HeadroomPlugin::get()->authorize(fn (): mixed => $result);

        expect(AuthorisedPage::canAccess())->toBeFalse();
    })->with([1, 'yes', [[true]], null]);

    it('mounts the page when allowed', function (): void {
        HeadroomPlugin::get()->authorize(true);
        $this->actingAs(demoUser());

        livewire(AuthorisedPage::class)->assertOk();
    });
});

describe('panels without the plugin', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel(Panel::make()->id('host'));
    });

    it('has no current plugin', function (): void {
        expect(HeadroomPlugin::current())->toBeNull();
    });

    it('ignores the plugin callback and falls back to the default rule', function (): void {
        Filament::getPanel('admin')->getPlugin(HeadroomPlugin::ID)->authorize(true);

        useEnvironment('production');
        expect(AuthorisedWidget::canView())->toBeFalse();

        useEnvironment('local');
        expect(AuthorisedWidget::canView())->toBeTrue();
    });
});
