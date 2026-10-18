<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Phoenix1331\AiHeadroom\Enums\CountMethod;
use Phoenix1331\AiHeadroom\Enums\LimitType;
use Phoenix1331\AiHeadroomLedger\Enums\Outcome;
use Phoenix1331\FilamentHeadroom\Enums\ValueBasis;
use Phoenix1331\FilamentHeadroom\FilamentHeadroomServiceProvider;
use Phoenix1331\FilamentHeadroom\Support\CalibrationStatus;
use Symfony\Component\Finder\Finder;

/*
 * Every user-facing string lives in resources/lang/en, so the dashboard can
 * be translated by publishing and editing those files.
 */

/**
 * @return array<string, string> path => contents
 */
function sources(string $directory, string $pattern): array
{
    $files = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/'.$directory)->name($pattern) as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

it('passes no plain string to Filament labels, headings or descriptions', function (): void {
    $methods = 'label|heading|description|modalHeading|modalDescription|modalSubmitActionLabel|emptyStateHeading|emptyStateDescription|helperText|tooltip|title|placeholder|body';

    foreach (sources('src', '*.php') as $path => $code) {
        preg_match_all("/->(?:{$methods})\\(\\s*['\"]([A-Za-z][^'\"]*)['\"]/", $code, $matches);
        preg_match_all("/Stat::make\\(\\s*['\"]([^'\"]+)['\"]/", $code, $stats);

        expect([...$matches[1], ...$stats[1]])->toBe([], "Hard-coded text in src/{$path}");
    }
});

it('keeps literal text out of views', function (): void {
    foreach (sources('resources/views', '*.blade.php') as $path => $view) {
        // Drop PHP blocks, echoes and PHP arrows (whose ">" reads as a tag end), then directives.
        $markup = preg_replace(['/@php.*?@endphp/s', '/\{\{.*?\}\}/s', '/->/', '/@\w+(\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/'], '', $view) ?? '';

        preg_match_all('/>\s*([A-Za-z][^<]*?)\s*</', $markup, $matches);

        expect($matches[1])->toBe([], "Literal text in resources/views/{$path}");
    }
});

it('defines every translation key the plugin uses', function (): void {
    $keys = [];

    foreach ([...sources('src', '*.php'), ...sources('resources/views', '*.blade.php')] as $code) {
        preg_match_all("/['\"](filament-headroom::[a-z_]+(?:\\.[a-z_]+)+)['\"]/", $code, $matches);
        array_push($keys, ...$matches[1]);
    }

    // Keys built from a value at runtime, expanded for every value they can take.
    foreach (Outcome::cases() as $outcome) {
        $keys[] = "filament-headroom::outcomes.{$outcome->value}";
    }

    foreach (LimitType::cases() as $limit) {
        $keys[] = "filament-headroom::outcomes.reasons.{$limit->value}";
    }

    foreach (CountMethod::cases() as $method) {
        $keys[] = "filament-headroom::count_methods.{$method->value}";
    }

    foreach (ValueBasis::cases() as $basis) {
        if (! $basis->isReported()) {
            $keys[] = "filament-headroom::basis.{$basis->value}.label";
            $keys[] = "filament-headroom::basis.{$basis->value}.tooltip";
        }
    }

    foreach ([CalibrationStatus::OFF, CalibrationStatus::NONE, CalibrationStatus::LEARNING, CalibrationStatus::ACTIVE] as $state) {
        $keys[] = "filament-headroom::calibration.{$state}";
    }

    foreach (['instructions', 'history', 'tools', 'attachments', 'prompt'] as $part) {
        $keys[] = "filament-headroom::pages.detail.parts.{$part}";
    }

    foreach (['estimated_input', 'actual_input', 'output', 'cache_read', 'cache_write', 'context_window'] as $token) {
        $keys[] = "filament-headroom::pages.detail.{$token}";
    }

    $keys = array_values(array_unique($keys));
    $missing = array_values(array_filter($keys, fn (string $key): bool => ! trans()->hasForLocale($key, 'en')));

    expect($keys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
});

it('ships no em dashes in any translation', function (): void {
    foreach (sources('resources/lang', '*.php') as $path => $lines) {
        expect($lines)->not->toContain('—', "Em dash in resources/lang/{$path}");
    }
});

it('publishes its translations for apps to edit', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        FilamentHeadroomServiceProvider::class,
        'filament-headroom-translations',
    );

    expect(array_values($paths))->toBe([app()->langPath('vendor/filament-headroom')])
        ->and(is_dir((string) array_key_first($paths)))->toBeTrue();
});
