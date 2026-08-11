<?php

namespace App\Http\Controllers\Web\Production;

use App\Http\Controllers\Concerns\ServesBothSections;
use App\Http\Controllers\Controller;
use App\Models\Line;
use App\Models\Workstation;
use App\Services\Production\ShiftMonitorService;
use App\Support\ShiftWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One line's machines, side by side, for the shift that is running.
 *
 * The shift monitor answers "explain this machine's shift"; this answers the
 * question asked before it — "which of my machines is in trouble" — so it is a
 * row per station rather than a row per hour, and clicking one opens the
 * monitor on that station.
 *
 * Deliberately the shift and not the day: targets, OEE and the handover are all
 * per shift, so a 24-hour axis would carry numbers with no single meaning, and
 * a day squeezed into one screen width leaves a two-minute stop two pixels
 * wide — too small to see and far too small to click.
 *
 * Same access as the monitor (`tab:shift_monitor`), and served under both the
 * admin and supervisor prefixes by the same controller.
 */
class ShiftOverviewController extends Controller
{
    use ServesBothSections;

    public function __construct(private readonly ShiftMonitorService $monitor) {}

    public function index(Request $request): Response
    {
        [$line, $window] = $this->resolveTarget($request);

        return Inertia::render('production/shift-overview/Index', [
            'lines' => $this->lines(),
            'selected' => $line ? ['lineId' => $line->id] : null,
            'snapshot' => $line ? $this->snapshot($line, $window) : null,
            'basePath' => $this->basePath('/shift-overview'),
            'monitorPath' => $this->basePath('/shift-monitor'),
        ]);
    }

    /** Polled/pushed refresh — the same payload the page was rendered with. */
    public function check(Request $request): JsonResponse
    {
        [$line, $window] = $this->resolveTarget($request);

        if (! $line) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $this->snapshot($line, $window),
            'selected' => ['lineId' => $line->id],
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(Line $line, ShiftWindow $window): array
    {
        $stations = $this->stations($line);

        return [
            'line' => ['id' => $line->id, 'name' => $line->name],
            'shift' => [
                'label' => $window->start->translatedFormat('l d.m').' · '.($window->shift?->name ?? __('Shift')),
                'window' => $window->start->format('H:i').'–'.$window->end->format('H:i'),
                'isLive' => $window->contains(now()),
            ],
            // Ids the client subscribes to: each station pushes on its own
            // channel, exactly as it does for the detail screen.
            'stationIds' => $stations->pluck('id')->all(),
            'stations' => $this->monitor->fleet($stations, $window),
            'clock' => ['iso' => now()->toIso8601String()],
        ];
    }

    /**
     * The line's machines, in a stable order.
     *
     * Every active workstation on the line, not only the ones wired to a
     * collector: a station that reports nothing is exactly what this screen
     * should make visible, and hiding it would answer "all good" for a machine
     * nobody is hearing from.
     *
     * @return Collection<int, Workstation>
     */
    private function stations(Line $line): Collection
    {
        return Workstation::where('line_id', $line->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    private function lines(): array
    {
        return Line::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /**
     * Which line and shift the request is asking about.
     *
     * @return array{0: ?Line, 1: ShiftWindow}
     */
    private function resolveTarget(Request $request): array
    {
        $line = $request->integer('line')
            ? Line::find($request->integer('line'))
            : null;

        $line ??= Line::where('is_active', true)->orderBy('name')->first();

        // The shift running on that line now. Paging through past shifts is the
        // detail screen's job — this one is about what is happening.
        $window = $line
            ? ShiftWindow::current($line->id)
            : ShiftWindow::current();

        return [$line, $window];
    }
}
