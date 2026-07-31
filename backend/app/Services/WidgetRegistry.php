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
 */
class WidgetRegistry
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $widgets = [];

    /** Fields a widget may carry; anything else is ignored (keeps the prop lean and safe). */
    private const ALLOWED = ['title', 'metric', 'body', 'href', 'external', 'tone'];

    /**
     * Register a dashboard card for a zone.
     *
     * @param  string  $zone  kpi | main | sidebar
     * @param  array<string, mixed>  $widget  title (required) + optional metric/body/href/external/tone
     * @param  int  $order  Sort weight within the zone — lower renders first
     */
    public function register(string $zone, array $widget, int $order = 50): void
    {
        $card = array_intersect_key($widget, array_flip(self::ALLOWED));
        $card['order'] = $order;

        $this->widgets[$zone][] = $card;
    }

    /**
     * Widgets for one zone, sorted by order.
     *
     * @return list<array<string, mixed>>
     */
    public function getWidgets(string $zone): array
    {
        $widgets = $this->widgets[$zone] ?? [];
        usort($widgets, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $widgets;
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
