<?php

namespace App\Services;

class WidgetRegistry
{
    private array $widgets = [];

    /**
     * Register a widget for a dashboard zone.
     *
     * Modules call this in their ServiceProvider::boot():
     *   app(WidgetRegistry::class)->register('admin_dashboard.main', 'mymodule::widgets.my-widget', ['key' => $value], 50);
     *
     * Built-in zones:
     *   admin_dashboard.kpi    — after the KPI cards grid
     *   admin_dashboard.main   — below the main work orders / sidebar grid
     *   admin_dashboard.sidebar — inside the sidebar column
     *
     * @param string $zone   Zone identifier
     * @param string $view   Blade view name (supports namespaced views from modules)
     * @param array  $data   Data to pass to the view
     * @param int    $order  Sort order — lower numbers render first
     */
    public function register(string $zone, string $view, array $data = [], int $order = 50): void
    {
        $this->widgets[$zone][] = compact('view', 'data', 'order');
    }

    /**
     * Return widgets for a zone sorted by order.
     *
     * @return array<array{view: string, data: array, order: int}>
     */
    public function getWidgets(string $zone): array
    {
        $widgets = $this->widgets[$zone] ?? [];
        usort($widgets, fn($a, $b) => $a['order'] <=> $b['order']);
        return $widgets;
    }

    public function hasWidgets(string $zone): bool
    {
        return !empty($this->widgets[$zone]);
    }
}
