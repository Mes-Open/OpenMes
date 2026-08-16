/**
 * Pure planner layout math — a typed port of the web planner's
 * `Pages/admin/schedule/planner/helpers.js`. Deliberately DOM-free and
 * dependency-free so both surfaces place blocks identically; anything that
 * touched CSS variables or the DOM stayed behind on the web side.
 *
 * Keep the column/lane rules here in sync with the web file — if the two drift,
 * the same order lands in different cells on tablet and desktop.
 */

import type { PlannerOrder } from '@openmes/ui/planner';

// Scheduling maths and the shapes it works on live in @openmes/ui — one
// implementation, so the two planners can't place the same order differently.
export {
    chainChipMeta,
    dayList,
    fmtKey,
    fmtTime,
    hourlyLanes,
    isoWeek,
    lineLoad,
    minuteOfDay,
    onLine,
    onMonthlyDay,
    parseDate,
    placementsOf,
    projectSegment,
    segmentChain,
    todayKey,
    weeklyPlacements,
    weeklySlot,
} from '@openmes/ui/planner';
export type { PlacementKey, Segment, PlannerOrder, PlannerPlacement, PlannerDay, ChainChip, WeeklyItem, HourlyItem } from '@openmes/ui/planner';

// Also used by the mobile-only helpers below.
import { segmentChain } from '@openmes/ui/planner';
import type { PlacementKey, Segment, PlannerDay, WeeklyItem } from '@openmes/ui/planner';

// ── Dates ──────────────────────────────────────────────────────────────────

// ── Multi-segment placements ───────────────────────────────────────────────
// An order runs one primary placement (the order's own line/date columns —
// the minute plan lives there) plus any number of coarse extra segments.

// ── Weekly placement ───────────────────────────────────────────────────────

/** Inverse of the column formula — which day/shift a column index lands on. */
export function slotOfCol(
  col: number,
  days: PlannerDay[],
  shiftsPerDay: number,
): { date: string; shift: number } | null {
  const dayIndex = Math.floor(col / shiftsPerDay);
  if (dayIndex < 0 || dayIndex >= days.length) return null;
  return { date: days[dayIndex].date, shift: (col % shiftsPerDay) + 1 };
}

/** 'HH:MM' for a minute-of-day, wrapping safely. */
export function fmtMin(m: number): string {
  const v = ((Math.round(m) % 1440) + 1440) % 1440;
  const p = (n: number) => (n < 10 ? '0' + n : String(n));
  return `${p(Math.floor(v / 60))}:${p(v % 60)}`;
}

// ── Hourly layout ──────────────────────────────────────────────────────────

// ── Chain connectors ───────────────────────────────────────────────────────

export interface Point {
  x: number;
  y: number;
}

export interface Connector {
  id: string;
  /** Polyline vertices, already in grid-content coordinates. */
  points: Point[];
  /** End caps — drawn on the two segments being joined. */
  dots: Point[];
}

/** A rendered row: only what the connector geometry needs to read. */
export interface ConnectorRow {
  height: number;
  items: WeeklyItem[];
}

export interface ConnectorGeometry {
  cellW: number;
  laneH: number;
  /** Height of the day + shift headers the rows are offset by. */
  topOffset: number;
  /** Inset applied to each block, so the line meets the drawn edge. */
  blockInset: number;
}

/**
 * Accent connectors joining each pair of chain neighbours in a multi-segment
 * order: a vertical bridge when the two segments overlap in time (they run
 * concurrently), otherwise an elbow tracing the staircase step.
 *
 * The web equivalent (TwinConnectors) measures rendered DOM nodes with
 * getBoundingClientRect. Here the grid geometry is deterministic, so the same
 * result falls out of arithmetic — no measurement, no async layout pass, and
 * nothing to re-measure on resize.
 */
export function weeklyConnectors(
  orders: PlannerOrder[],
  rows: ConnectorRow[],
  geo: ConnectorGeometry,
): Connector[] {
  // Index every rendered segment's rect by order + placement key.
  const rects = new Map<string, { left: number; right: number; top: number; bottom: number; midY: number }>();
  let rowTop = geo.topOffset;
  rows.forEach((row) => {
    row.items.forEach((it) => {
      const top = rowTop + 4 + it.lane * geo.laneH;
      const bottom = top + geo.laneH - 6;
      rects.set(`${it.wo.id}:${String(it.placementKey)}`, {
        left: it.startCol * geo.cellW + geo.blockInset,
        right: (it.endCol + 1) * geo.cellW - geo.blockInset,
        top,
        bottom,
        midY: (top + bottom) / 2,
      });
    });
    rowTop += row.height;
  });

  const out: Connector[] = [];
  orders
    .filter((o) => (o.placements ?? []).length > 0)
    .forEach((wo) => {
      const chain = segmentChain(wo);
      for (let i = 0; i < chain.length - 1; i++) {
        const a = rects.get(`${wo.id}:${String(chain[i].key)}`);
        const b = rects.get(`${wo.id}:${String(chain[i + 1].key)}`);
        // A segment on a filtered-out line simply isn't drawn — skip its link.
        if (!a || !b) continue;

        const xOverlap = Math.min(a.right, b.right) - Math.max(a.left, b.left);
        if (xOverlap > 0) {
          // Concurrent segments: bridge straight down through the overlap.
          const [upper, lower] = a.top <= b.top ? [a, b] : [b, a];
          const x = (Math.max(a.left, b.left) + Math.min(a.right, b.right)) / 2;
          const y1 = upper.bottom;
          const y2 = lower.top;
          out.push({
            id: `${wo.id}:${i}`,
            points: [
              { x, y: y1 },
              { x, y: y2 },
            ],
            dots: [
              { x, y: y1 },
              { x, y: y2 },
            ],
          });
        } else {
          // Staircase step: elbow from the earlier segment's end to the later
          // segment's start, turning at the horizontal midpoint.
          const [e, l] = a.left <= b.left ? [a, b] : [b, a];
          const x1 = e.right;
          const y1 = e.midY;
          const x2 = l.left;
          const y2 = l.midY;
          const xm = (x1 + x2) / 2;
          out.push({
            id: `${wo.id}:${i}`,
            points: [
              { x: x1, y: y1 },
              { x: xm, y: y1 },
              { x: xm, y: y2 },
              { x: x2, y: y2 },
            ],
            dots: [
              { x: x1, y: y1 },
              { x: x2, y: y2 },
            ],
          });
        }
      }
    });
  return out;
}
