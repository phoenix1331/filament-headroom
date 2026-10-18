<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom;

use Filament\Contracts\Plugin;
use Filament\Panel;
use LogicException;

class HeadroomPlugin implements Plugin
{
    public const ID = 'headroom';

    public static function make(): static
    {
        return self::ensureInstance(app(static::class));
    }

    public static function get(): static
    {
        return self::ensureInstance(filament(self::ID));
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

    private static function ensureInstance(mixed $plugin): static
    {
        if (! $plugin instanceof static) {
            throw new LogicException('Plugin ['.self::ID.'] is not an instance of '.static::class.'.');
        }

        return $plugin;
    }
}
