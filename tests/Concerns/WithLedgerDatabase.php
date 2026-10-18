<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Migrates the ledger's tables into the in-memory SQLite database. The
 * ledger publishes its migrations rather than loading them, so tests point
 * at them directly, as the workbench does.
 */
trait WithLedgerDatabase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/vendor/phoenix1331/laravel-ai-headroom-ledger/database/migrations');
    }
}
