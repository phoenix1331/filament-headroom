<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Ledger settings an app would put in config/headroom-ledger.php: the tags
 * the seeder records, so the Requests page offers tag filters.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'headroom-ledger.tags.allowed' => ['tenant_id', 'feature'],
            'headroom-ledger.tags.primary_tag' => 'tenant_id',
        ]);
    }
}
