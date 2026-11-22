<?php

namespace App\Services;

/**
 * WidgetRegistry — lets modules add cards to the admin dashboard.
 *
 * Registered as a singleton in AppServiceProvider. Modules call register() in
 * their ServiceProvider::boot(); only ENABLED modules boot, so the registry is
 * self-gating.
 *
 * React-native contract: a widget is STRUCTURED DATA (not a Blade view). The
 * dashboard is a React/Inertia page, so it renders a standard card from these
 * fields — every string is escaped by React (no raw HTML, no dangerouslySetInnerHTML).
 * DashboardController exposes the registry as the `moduleWidgets` Inertia prop.
 *
 * Zones (map onto the React dashboard layout):
 *   kpi     — compact metric card in the KPI grid near the top
 *   main    — full-width card in the main column
 *   sidebar — compact card (rendered alongside the KPI cards)
 *
 * Usage in a module ServiceProvider::boot():
 *   app(WidgetRegistry::class)->register('kpi', [
 *       'title'  => 'Open jobs',
 *       'metric' => (string) $count,
 *       'body'   => 'Awaiting start',
 *       'href'   => url('/modules/mymodule'),   // optional
 *       'external' => true,                       // optional: full page load
 *   ], order: 50);
 *
 * A value that changes (a count, a status) must not be computed in boot(): under
 * Octane the provider boots once per worker, so it would freeze at that moment.
 * Pass a callable instead – it is called every time the dashboard is rendered and
 * returns the card (or null to show nothing):
 *   app(WidgetRegistry::class)->register('kpi', fn () => [
 *       'title'  => 'Open jobs',
 *       'metric' => (string) Job::open()->count(),
 *   ]);
 */
class WidgetRegistry
{
    /** @var array<string, list<array{order: int, card: array<string, mixed>|callable}>> */
    private array $widgets = [];

    /** Fields a widget may carry; anything else is ignored (keeps the prop lean and safe). */
    private const ALLOWED = ['title', 'metric', 'body', 'href', 'external', 'tone'];

    /**
     * Register a dashboard card for a zone.
     *
     * @param  string  $zone  kpi | main | sidebar
     * @param  array<string, mixed>|callable(): ?array<string, mixed>  $widget  title (required) + optional metric/body/href/external/tone, or a callable returning that per render
     * @param  int  $order  Sort weight within the zone — lower renders first
     */
    public function register(string $zone, array|callable $widget, int $order = 50): void
    {
        $this->widgets[$zone][] = ['order' => $order, 'card' => $widget];
    }

    /**
     * Widgets for one zone, sorted by order. A callable is resolved now; one that
     * returns null or an empty card is left out.
     *
     * @return list<array<string, mixed>>
     */
    public function getWidgets(string $zone): array
    {
        $cards = [];
        foreach ($this->widgets[$zone] ?? [] as $entry) {
            $card = is_callable($entry['card']) ? ($entry['card'])() : $entry['card'];
            if (! is_array($card) || $card === []) {
                continue;
            }
            $cards[] = [...array_intersect_key($card, array_flip(self::ALLOWED)), 'order' => $entry['order']];
        }
        usort($cards, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $cards;
    }

    public function hasWidgets(string $zone): bool
    {
        return ! empty($this->widgets[$zone]);
    }

    /**
     * Every zone's widgets, sorted — the shape handed to the React dashboard.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(): array
    {
        $out = [];
        foreach (array_keys($this->widgets) as $zone) {
            $out[$zone] = $this->getWidgets($zone);
        }

        return $out;
    }
}
