<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroomLedger\Database\Factories\LedgerEntryFactory;
use Workbench\App\Models\User;

/**
 * Demo data for the workbench panel and screenshots: 90 days of ledger
 * entries across three tenants, every outcome, failover, approximate costs,
 * streamed and abandoned requests and multi-step agent runs, then rolled up
 * so the daily aggregates match.
 */
class DatabaseSeeder extends Seeder
{
    private const int DAYS = 90;

    /** @var list<string> */
    private const array TENANTS = ['acme', 'globex', 'initech'];

    /** @var list<string> */
    private const array FEATURES = ['chat', 'search', 'reports'];

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);

        foreach (self::TENANTS as $tenant) {
            $this->seedTenant($tenant);
        }

        $this->command->call('headroom-ledger:rollup', [
            '--from' => Carbon::today()->subDays(self::DAYS)->toDateString(),
        ]);
    }

    private function seedTenant(string $tenant): void
    {
        for ($day = self::DAYS; $day >= 0; $day--) {
            $date = Carbon::today()->subDays($day);

            // Weekday traffic is heavier, and usage grows over the period.
            $volume = ($date->isWeekend() ? 2 : 5) + intdiv(self::DAYS - $day, 15);

            for ($i = 0; $i < $volume; $i++) {
                $this->entry($tenant, $this->moment($date))->create();
            }

            if ($day % 3 === 0) {
                $this->entry($tenant, $this->moment($date))->warned()->create();
            }

            if ($day % 7 === 0) {
                $this->entry($tenant, $this->moment($date))->blocked()->create();
            }

            if ($day % 9 === 0) {
                $this->entry($tenant, $this->moment($date))->failed()->create();
            }

            if ($day % 5 === 0) {
                $this->entry($tenant, $this->moment($date))->streamed()->create();
                $this->entry($tenant, $this->moment($date))->failover()->create();
                $this->entry($tenant, $this->moment($date))->approximateCost()->create();
            }

            if ($day % 11 === 0) {
                $this->entry($tenant, $this->moment($date))->abandoned()->create();
            }

            if ($day % 4 === 0) {
                $this->entry($tenant, $this->moment($date))
                    ->count(random_int(3, 6))
                    ->inRun()
                    ->create();
            }
        }
    }

    private function entry(string $tenant, Carbon $at): LedgerEntryFactory
    {
        $model = Arr::random(array_keys(LedgerEntryFactory::MODELS));

        return LedgerEntryFactory::new()
            ->forModel($model)
            ->countMethod(Arr::random(CountMethod::cases()))
            ->at($at)
            ->withTags([
                'tenant_id' => $tenant,
                'feature' => Arr::random(self::FEATURES),
            ]);
    }

    private function moment(Carbon $date): Carbon
    {
        $moment = $date->copy()->addHours(random_int(8, 19))->addMinutes(random_int(0, 59));

        return $moment->isFuture() ? Carbon::now()->subMinutes(random_int(1, 30)) : $moment;
    }
}
