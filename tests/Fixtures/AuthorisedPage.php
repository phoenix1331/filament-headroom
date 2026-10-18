<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Tests\Fixtures;

use Filament\Pages\Page;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesPageAccess;

class AuthorisedPage extends Page
{
    use AuthorisesPageAccess;

    protected string $view = 'filament-panels::pages.page';
}
