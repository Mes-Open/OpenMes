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
 * The whole plant on one screen — the board that hangs above the floor.
 *
 * The three shift screens answer three different questions and this is the
 * broadest of them: the monitor explains one machine's shift, the overview
 * compares the machines on one line, and this one says which machines in the
 * building are stopped and why. Nobody stands in front of it and drills down,
 * so it carries no timeline and no controls — a tile, a colour, a cause and a
 * clock.
 *
 * Deliberately flat: no grouping by line. A board is read as a scoreboard, and
 * grouping turns it into a structure tree that has to be parsed before the red
 * tile is found. The line is printed on the tile instead, which is where it
 * matters once you have spotted one.
 *
 * One shift window for the whole plant (`ShiftWindow::current()` with no line):
 * per-line windows would put numbers covering different spans of time side by
 * side under one heading.
 */
class ShiftBoardController extends Controller
{
    use ServesBothSections;

    public function __construct(private readonly ShiftMonitorService $monitor) {}

    public function index(Request $request): Response
    {
        return Inertia::render('production/shift-board/Index', [
            'lines' => $this->lines(),
            'selected' => ['lineIds' => $this->requestedLineIds($request)],
            'snapshot' => $this->snapshot($request),
            'basePath' => $this->basePath('/shift-board'),
            'monitorPath' => $this->basePath('/shift-monitor'),
            // `?kiosk=1` strips the app chrome. Resolved here rather than in
            // the page so the wall display's whole configuration is the URL —
            // one string to paste into a browser that nobody will touch again.
            'kiosk' => $request->boolean('kiosk'),
        ]);
    }

    /** Pushed/polled refresh — the same payload the page was rendered with. */
    public function check(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->snapshot($request)]);
    }

    /** @return array<string, mixed> */
    private function snapshot(Request $request): array
    {
        $window = ShiftWindow::current();
        $stations = $this->stations($this->requestedLineIds($request));

        return [
            'shift' => [
                'label' => $window->start->translatedFormat('l d.m').' · '.($window->shift?->name ?? __('Shift')),
                'window' => $window->start->format('H:i').'–'.$window->end->format('H:i'),
                'isLive' => $window->contains(now()),
            ],
            // Ids the client subscribes to: each station pushes on its own
            // channel, exactly as the line overview does.
            'stationIds' => $stations->pluck('id')->all(),
            'stations' => $this->monitor->board($stations, $window),
            'clock' => ['iso' => now()->toIso8601String()],
        ];
    }

    /**
     * The stations on the board, in a stable order.
     *
     * Every active station, including ones no collector has ever reported —
     * a machine nobody is hearing from is exactly what a board exists to make
     * visible, and hiding it would silently answer "all good".
     *
     * @param  array<int, int>|null  $lineIds  null = the whole plant
     * @return Collection<int, Workstation>
     */
    private function stations(?array $lineIds): Collection
    {
        return Workstation::with('line')
            ->where('is_active', true)
            ->when($lineIds !== null, fn ($q) => $q->whereIn('line_id', $lineIds))
            ->orderBy('code')
            ->get();
    }

    /**
     * Lines the kiosk URL narrowed the board to (`?lines=3,7`), filtered to
     * ones that exist. Null when the URL asked for no narrowing at all.
     *
     * A filter that matches nothing stays an empty list rather than collapsing
     * back to null: a wall display quietly showing the whole plant because a
     * line was renumbered is a failure nobody in the building would notice.
     *
     * @return array<int, int>|null
     */
    private function requestedLineIds(Request $request): ?array
    {
        $raw = $request->query('lines');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $ids = collect(explode(',', $raw))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique();

        return $ids->isEmpty() ? [] : Line::whereIn('id', $ids)->pluck('id')->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function lines(): array
    {
        return Line::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }
}
