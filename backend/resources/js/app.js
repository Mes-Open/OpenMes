import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

// Make Chart.js available globally
window.Chart = Chart;

// Only start Alpine if Livewire hasn't already loaded it.
// Livewire 4 bundles its own Alpine — on pages with Livewire components
// Alpine is already running. On pages without Livewire (e.g. /operator/queue)
// we need to provide it ourselves.
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}
