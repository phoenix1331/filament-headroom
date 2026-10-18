<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom\Support;

use Phoenix1331\AiHeadroom\Enums\LimitType;
use Phoenix1331\AiHeadroomLedger\Enums\Outcome;

/**
 * Labels, badge colours and readable reasons for ledger outcomes, shared by
 * every table and detail view. The ledger stores a reason as a limit type or
 * as "ExceptionClass:status", never a message.
 */
final class Outcomes
{
    public static function label(Outcome $outcome): string
    {
        return __("filament-headroom::outcomes.{$outcome->value}");
    }

    public static function color(Outcome $outcome): string
    {
        return match ($outcome) {
            Outcome::Completed => 'success',
            Outcome::Counted => 'gray',
            Outcome::Warned => 'warning',
            Outcome::Blocked, Outcome::Failed => 'danger',
        };
    }

    public static function reason(?string $reason): ?string
    {
        if ($reason === null || $reason === '') {
            return null;
        }

        $limit = LimitType::tryFrom($reason);

        if ($limit !== null) {
            return __("filament-headroom::outcomes.reasons.{$limit->value}");
        }

        $parts = explode(':', $reason, 2);
        $name = class_basename($parts[0]);
        $status = $parts[1] ?? null;

        if ($status === null || $status === '') {
            return $name;
        }

        return __('filament-headroom::outcomes.reasons.exception_status', ['exception' => $name, 'status' => $status]);
    }
}
