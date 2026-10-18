<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Tests\Fixtures;

use Filament\Widgets\Widget;
use Phoenix1331\FilamentHeadroom\Concerns\AuthorisesWidgetView;

class AuthorisedWidget extends Widget
{
    use AuthorisesWidgetView;

    protected string $view = 'filament-widgets::stats-overview-widget';
}
