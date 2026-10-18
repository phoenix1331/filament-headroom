<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom;

use Filament\Contracts\Plugin;
use Filament\Panel;

class HeadroomPlugin implements Plugin
{
    public const ID = 'headroom';

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
