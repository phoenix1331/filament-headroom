<?php

declare(strict_types=1);

namespace Phoenix1331\FilamentHeadroom;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use InvalidArgumentException;
use LogicException;
use Phoenix1331\FilamentHeadroom\Enums\Range;
use UnitEnum;

class HeadroomPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'headroom';

    protected bool|Closure|null $authorizeUsing = null;

    protected string|UnitEnum|Closure|null $navigationGroup = null;

    protected bool $hasNavigationGroup = false;

    protected string|BackedEnum|Htmlable|Closure|null $navigationIcon = null;

    /** @var array<class-string<Page>, string|BackedEnum|Htmlable|Closure> */
    protected array $pageNavigationIcons = [];

    protected int|Closure|null $navigationSort = null;

    protected Range|string|Closure $defaultRange = Range::DEFAULT;

    protected ?Closure $tagLabelUsing = null;

    public static function make(): static
    {
        return self::ensureInstance(app(static::class));
    }

    public static function get(): static
    {
        return self::ensureInstance(filament(self::ID));
    }

    /**
     * The plugin on the current panel, or null when that panel does not use it,
     * for example a widget placed on a host app's own dashboard.
     */
    public static function current(): ?static
    {
        if (Filament::getCurrentOrDefaultPanel()?->hasPlugin(self::ID) !== true) {
            return null;
        }

        return static::get();
    }

    /**
     * Whether the signed-in user may see Headroom pages and widgets on the
     * current panel. Without the plugin there is no callback, so the default
     * rule applies.
     */
    public static function allows(): bool
    {
        return static::current()?->isAuthorized() ?? self::defaultAuthorization();
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Who may see the dashboard. The closure can take `$user` (the panel's
     * signed-in user, or null) and must return true to allow access; any other
     * value denies. Without this, access is allowed only in the local environment.
     */
    public function authorize(bool|Closure $condition): static
    {
        $this->authorizeUsing = $condition;

        return $this;
    }

    public function isAuthorized(?Authenticatable $user = null): bool
    {
        if ($this->authorizeUsing === null) {
            return self::defaultAuthorization();
        }

        $user ??= Filament::auth()->user();

        return $this->evaluate(
            $this->authorizeUsing,
            namedInjections: ['user' => $user],
            typedInjections: $user === null ? [] : [Authenticatable::class => $user, $user::class => $user],
        ) === true;
    }

    /**
     * The navigation group for every Headroom page. Defaults to "Headroom"
     * (translated); null lists the pages ungrouped.
     */
    public function navigationGroup(string|UnitEnum|Closure|null $group): static
    {
        $this->navigationGroup = $group;
        $this->hasNavigationGroup = true;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        if (! $this->hasNavigationGroup) {
            return __('filament-headroom::navigation.group');
        }

        $group = $this->evaluate($this->navigationGroup);

        if ($group === null || is_string($group) || $group instanceof UnitEnum) {
            return $group;
        }

        throw new InvalidArgumentException('The Headroom navigation group must resolve to a string, an enum or null, '.get_debug_type($group).' given.');
    }

    /**
     * The navigation icon for every Headroom page, or for one page when
     * $page is given. Pages keep their own icons when neither is set.
     *
     * @param  class-string<Page>|null  $page
     */
    public function navigationIcon(string|BackedEnum|Htmlable|Closure $icon, ?string $page = null): static
    {
        if ($page === null) {
            $this->navigationIcon = $icon;

            return $this;
        }

        $this->pageNavigationIcons[$page] = $icon;

        return $this;
    }

    /**
     * @param  class-string<Page>  $page
     */
    public function getNavigationIcon(string $page): string|BackedEnum|Htmlable|null
    {
        $icon = $this->evaluate($this->pageNavigationIcons[$page] ?? $this->navigationIcon);

        if ($icon === null || is_string($icon) || $icon instanceof BackedEnum || $icon instanceof Htmlable) {
            return $icon;
        }

        throw new InvalidArgumentException('A Headroom navigation icon must resolve to a string, an enum or Htmlable, '.get_debug_type($icon).' given.');
    }

    /**
     * Where the block of Headroom pages sits in the navigation. Each page's
     * own sort becomes its position within the block.
     */
    public function navigationSort(int|Closure|null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        $sort = $this->evaluate($this->navigationSort);

        if ($sort === null || is_int($sort)) {
            return $sort;
        }

        throw new InvalidArgumentException('The Headroom navigation sort must resolve to an integer or null, '.get_debug_type($sort).' given.');
    }

    /**
     * The range pages and widgets open with: '7d', '30d' (the default) or '90d'.
     */
    public function defaultRange(Range|string|Closure $range): static
    {
        if (is_string($range)) {
            self::toRange($range);
        }

        $this->defaultRange = $range;

        return $this;
    }

    public function getDefaultRange(): Range
    {
        return self::toRange($this->evaluate($this->defaultRange));
    }

    /**
     * How a ledger tag is shown in filters, columns and the detail view. The
     * closure can take `$key` and `$value` and must return a string, for
     * example to show a tenant's name instead of its ID.
     */
    public function tagLabel(?Closure $callback): static
    {
        $this->tagLabelUsing = $callback;

        return $this;
    }

    public function getTagLabel(string $key, string $value): string
    {
        if ($this->tagLabelUsing === null) {
            return __('filament-headroom::tags.label', ['key' => $key, 'value' => $value]);
        }

        $label = $this->evaluate($this->tagLabelUsing, ['key' => $key, 'value' => $value]);

        if (is_string($label)) {
            return $label;
        }

        throw new InvalidArgumentException('The Headroom tag label must resolve to a string, '.get_debug_type($label).' given.');
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Deny outside local, as Horizon and Pulse do, so the dashboard is never
     * accidentally public.
     */
    private static function defaultAuthorization(): bool
    {
        return app()->environment('local');
    }

    private static function toRange(mixed $range): Range
    {
        if ($range instanceof Range) {
            return $range;
        }

        if (is_string($range) && ($resolved = Range::tryFrom($range)) !== null) {
            return $resolved;
        }

        $allowed = implode(', ', array_map(fn (Range $case): string => "'{$case->value}'", Range::cases()));

        throw new InvalidArgumentException('The Headroom default range must be one of '.$allowed.', '.(is_string($range) ? "'{$range}'" : get_debug_type($range)).' given.');
    }

    private static function ensureInstance(mixed $plugin): static
    {
        if (! $plugin instanceof static) {
            throw new LogicException('Plugin ['.self::ID.'] is not an instance of '.static::class.'.');
        }

        return $plugin;
    }
}
