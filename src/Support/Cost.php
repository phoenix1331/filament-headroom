<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Number;
use Phoenix1331\AiHeadroomLedger\Data\Money;
use Phoenix1331\AiHeadroomLedger\Data\Spend;
use Phoenix1331\AiHeadroomLedger\Data\SpendSummary;
use Phoenix1331\FilamentHeadroom\Enums\ValueBasis;

/**
 * A ledger amount ready to show: formatted per currency (never converted or
 * summed across currencies) with the basis that says whether it is billed.
 * Every cost on every page and widget goes through this class.
 */
final readonly class Cost implements Htmlable
{
    /** Below this many micro-units (one cent) amounts show four decimals, so sub-cent costs do not read as zero. */
    private const int SUB_CENT = 10_000;

    /**
     * @param  list<string>  $amounts  One formatted amount per currency.
     */
    private function __construct(
        public array $amounts,
        public ValueBasis $basis,
    ) {}

    public static function fromMoney(?Money $money): self
    {
        if ($money === null) {
            return new self([], ValueBasis::Reported);
        }

        return new self([self::format($money)], self::basisOf([$money]));
    }

    public static function fromSpend(Spend $spend): self
    {
        return new self(
            array_map(self::format(...), $spend->all()),
            self::basisOf($spend->all()),
        );
    }

    /**
     * A summary knows whether its total is wholly or partly estimated, which
     * a single Money flag cannot tell apart.
     */
    public static function fromSummary(SpendSummary $summary): self
    {
        $cost = self::fromSpend($summary->total);

        if (! $summary->isEstimated() || $cost->amounts === []) {
            return $cost;
        }

        $basis = $summary->requestsWithUsage === 0 ? ValueBasis::Estimated : ValueBasis::IncludesEstimates;

        return new self($cost->amounts, $basis);
    }

    public function isEmpty(): bool
    {
        return $this->amounts === [];
    }

    /**
     * The amounts alone, e.g. "$1.24" or "$1.24 + €0.50".
     */
    public function amount(): string
    {
        if ($this->isEmpty()) {
            return __('filament-headroom::basis.none');
        }

        return implode(' + ', $this->amounts);
    }

    /**
     * The amounts with the basis in words, for places a badge cannot go
     * (chart tooltips, exports, plain text): "$1.24 (estimate)".
     */
    public function label(): string
    {
        $basis = $this->basis->getLabel();

        if ($basis === null || $this->isEmpty()) {
            return $this->amount();
        }

        return __('filament-headroom::basis.with_amount', ['amount' => $this->amount(), 'basis' => mb_strtolower($basis)]);
    }

    /**
     * The amount with its badge, so a Cost can be dropped into any Filament
     * slot that accepts Htmlable (stat values, table cells, infolists).
     */
    public function toHtml(): string
    {
        return Blade::render('<x-filament-headroom::cost :cost="$cost" />', ['cost' => $this]);
    }

    private static function format(Money $money): string
    {
        $micro = abs($money->microUnits);
        $precision = $micro !== 0 && $micro < self::SUB_CENT ? 4 : 2;

        $formatted = Number::currency($money->amount(), in: $money->currency, locale: app()->getLocale(), precision: $precision);

        if ($formatted === false) {
            return number_format($money->amount(), $precision).' '.$money->currency;
        }

        return $formatted;
    }

    /**
     * An estimate anywhere outranks approximate pricing: it is the larger
     * departure from billed cost.
     *
     * @param  list<Money>  $amounts
     */
    private static function basisOf(array $amounts): ValueBasis
    {
        $basis = ValueBasis::Reported;

        foreach ($amounts as $money) {
            if ($money->estimated) {
                return ValueBasis::Estimated;
            }

            if ($money->approximate) {
                $basis = ValueBasis::Approximate;
            }
        }

        return $basis;
    }
}
