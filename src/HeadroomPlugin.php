<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;

class HeadroomPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'headroom';

    protected bool|Closure|null $authorizeUsing = null;

    public static function make(): static
    {
        return self::ensureInstance(app(static::class));
    }

    public static function get(): static
    {
        return self::ensureInstance(filament(self::ID));
    }

    /**
     * The plugin on the current panel, or null when that panel does not use it,
     * for example a widget placed on a host app's own dashboard.
     */
    public static function current(): ?static
    {
        if (Filament::getCurrentOrDefaultPanel()?->hasPlugin(self::ID) !== true) {
            return null;
        }

        return static::get();
    }

    /**
     * Whether the signed-in user may see Headroom pages and widgets on the
     * current panel. Without the plugin there is no callback, so the default
     * rule applies.
     */
    public static function allows(): bool
    {
        return static::current()?->isAuthorized() ?? self::defaultAuthorization();
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Who may see the dashboard. The closure can take `$user` (the panel's
     * signed-in user, or null) and must return true to allow access; any other
     * value denies. Without this, access is allowed only in the local environment.
     */
    public function authorize(bool|Closure $condition): static
    {
        $this->authorizeUsing = $condition;

        return $this;
    }

    public function isAuthorized(?Authenticatable $user = null): bool
    {
        if ($this->authorizeUsing === null) {
            return self::defaultAuthorization();
        }

        $user ??= Filament::auth()->user();

        return $this->evaluate(
            $this->authorizeUsing,
            namedInjections: ['user' => $user],
            typedInjections: $user === null ? [] : [Authenticatable::class => $user, $user::class => $user],
        ) === true;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Deny outside local, as Horizon and Pulse do, so the dashboard is never
     * accidentally public.
     */
    private static function defaultAuthorization(): bool
    {
        return app()->environment('local');
    }

    private static function ensureInstance(mixed $plugin): static
    {
        if (! $plugin instanceof static) {
            throw new LogicException('Plugin ['.self::ID.'] is not an instance of '.static::class.'.');
        }

        return $plugin;
    }
}
