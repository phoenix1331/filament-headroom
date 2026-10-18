<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a figure comes from. Only Reported values are what a provider billed;
 * every other basis is shown with a badge, so an estimate never passes for cost.
 */
enum ValueBasis: string implements HasColor, HasLabel
{
    /** Reported by the provider: billed usage or cost. */
    case Reported = 'reported';

    /** Entirely Headroom's pre-flight estimate; no usage was reported. */
    case Estimated = 'estimated';

    /** A total where some requests never reported usage and count at their estimate. */
    case IncludesEstimates = 'includes_estimates';

    /** Reported, but cache tokens could not be priced exactly. */
    case Approximate = 'approximate';

    public function isReported(): bool
    {
        return $this === self::Reported;
    }

    public function getLabel(): ?string
    {
        if ($this->isReported()) {
            return null;
        }

        return __("filament-headroom::basis.{$this->value}.label");
    }

    public function getTooltip(): ?string
    {
        if ($this->isReported()) {
            return null;
        }

        return __("filament-headroom::basis.{$this->value}.tooltip");
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Reported => null,
            self::Estimated, self::IncludesEstimates => 'warning',
            self::Approximate => 'gray',
        };
    }
}
