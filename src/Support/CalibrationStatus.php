<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Support;

use Phoenix1331\AiHeadroom\Calibration\Calibrator;

/**
 * Where Headroom's self-calibration stands for one model: off, no data yet,
 * learning until it has the configured minimum of samples, or active with the
 * correction it applies to estimates.
 */
final readonly class CalibrationStatus
{
    public const string OFF = 'off';

    public const string NONE = 'none';

    public const string LEARNING = 'learning';

    public const string ACTIVE = 'active';

    private function __construct(
        public string $state,
        public int $samples = 0,
        public int $minSamples = 0,
        public ?float $factor = null,
        public ?float $margin = null,
    ) {}

    public static function for(string $model): self
    {
        if (! config()->boolean('ai-headroom.calibration.enabled', true)) {
            return new self(self::OFF);
        }

        $calibrator = app(Calibrator::class);
        $minSamples = config()->integer('ai-headroom.calibration.min_samples', 20);
        $correction = $calibrator->correctionFor($model);

        if ($correction !== null) {
            return new self(self::ACTIVE, $correction->samples, $minSamples, $correction->factor, $correction->margin);
        }

        $calibration = $calibrator->store()->get($model);

        if ($calibration === null) {
            return new self(self::NONE, minSamples: $minSamples);
        }

        return new self(self::LEARNING, $calibration->samples, $minSamples);
    }

    public function label(): string
    {
        return __("filament-headroom::calibration.{$this->state}", [
            'samples' => Format::number($this->samples),
            'min' => Format::number($this->minSamples),
            'factor' => Format::number(round($this->factor ?? 1.0, 2)),
            'margin' => Format::percent(($this->margin ?? 0.0) * 100),
        ]);
    }

    public function color(): string
    {
        return match ($this->state) {
            self::ACTIVE => 'success',
            self::LEARNING => 'info',
            default => 'gray',
        };
    }
}
