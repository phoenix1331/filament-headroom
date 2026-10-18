<?php

declare(strict_types=1);

arch('ledger data is read only through the ledger query API')
    ->expect('Phoenix1331\FilamentHeadroom')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\DatabaseManager',
        'Illuminate\Database\Query\Builder',
        'Phoenix1331\AiHeadroomLedger\Models',
    ]);

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('every file declares strict types')
    ->expect(['Phoenix1331\FilamentHeadroom', 'Phoenix1331\FilamentHeadroom\Tests'])
    ->toUseStrictTypes();
