<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A Filament tenant for scoping tests; never stored.
 */
class Team extends Model
{
    protected $guarded = [];

    public static function make(int $id, string $slug = ''): self
    {
        return (new self)->forceFill(['id' => $id, 'slug' => $slug]);
    }
}
