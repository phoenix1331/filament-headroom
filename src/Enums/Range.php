<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Enums;

use Carbon\CarbonImmutable;
use Filament\Support\Contracts\HasLabel;
use Phoenix1331\AiHeadroomLedger\Query\LedgerQuery;
use Phoenix1331\FilamentHeadroom\HeadroomPlugin;

/**
 * The date ranges every page and widget offers. A range covers today and the
 * days before it, matching the ledger's lastDays().
 */
enum Range: string implements HasLabel
{
    case SevenDays = '7d';
    case ThirtyDays = '30d';
    case NinetyDays = '90d';

    public const self DEFAULT = self::ThirtyDays;

    /**
     * Turn page, filter or query string state into a range. Anything
     * unrecognised falls back to the plugin's default range.
     */
    public static function resolve(mixed $state): self
    {
        if ($state instanceof self) {
            return $state;
        }

        if (is_string($state) && ($range = self::tryFrom($state)) !== null) {
            return $range;
        }

        return HeadroomPlugin::current()?->getDefaultRange() ?? self::DEFAULT;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $range) {
            $options[$range->value] = $range->getLabel();
        }

        return $options;
    }

    public function days(): int
    {
        return match ($this) {
            self::SevenDays => 7,
            self::ThirtyDays => 30,
            self::NinetyDays => 90,
        };
    }

    public function getLabel(): string
    {
        return __('filament-headroom::ranges.last_days', ['days' => $this->days()]);
    }

    /**
     * First and last day of the range, both inclusive.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function between(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();

        return [$today->subDays($this->days() - 1), $today];
    }

    /**
     * The same number of days immediately before the range, for trends.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function previous(?CarbonImmutable $today = null): array
    {
        [$from] = $this->between($today);

        return [$from->subDays($this->days()), $from->subDay()];
    }

    /**
     * Days per point for charts that cost one query per point.
     */
    public function bucketDays(): int
    {
        return match ($this) {
            self::SevenDays => 1,
            self::ThirtyDays => 3,
            self::NinetyDays => 7,
        };
    }

    /**
     * The range split into consecutive buckets of bucketDays(), oldest first,
     * ending today. The oldest bucket is shorter when the days do not divide.
     *
     * @return list<array{CarbonImmutable, CarbonImmutable}>
     */
    public function buckets(?CarbonImmutable $today = null): array
    {
        [$from, $to] = $this->between($today);
        $buckets = [];

        while ($to >= $from) {
            $start = $to->subDays($this->bucketDays() - 1)->max($from);
            array_unshift($buckets, [$start, $to]);
            $to = $start->subDay();
        }

        return $buckets;
    }

    public function apply(LedgerQuery $query): LedgerQuery
    {
        return $query->between(...$this->between());
    }
}
