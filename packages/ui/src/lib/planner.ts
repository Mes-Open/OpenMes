/**
 * Planner scheduling maths — the single implementation behind BOTH planners:
 * the web one (backend/resources/js/Pages/admin/schedule/planner) and the
 * mobile one (mobile/components/planner).
 *
 * Pure and platform-free: no DOM, no react-native, no styling. It answers
 * *where* an order sits — which day/shift cell, which lane, how loaded a line
 * is. It lived as two copies that had to be edited in lockstep, and the mobile
 * copy's own header warned that drift would put "the same order in different
 * cells on tablet and desktop". One copy now, so that can't happen.
 */

/** One extra placement as the API returns it. */
export interface PlannerPlacement {
  id: number;
  line_id: number;
  due_date: string;
  shift_number: number | null;
  end_date: string | null;
  end_shift_number: number | null;
}

/** A work order as the planner board consumes it. */
export interface PlannerOrder {
  id: number;
  order_no: string;
  customer_name: string | null;
  customer_tier: string | null;
  priority_score: number | null;
  product_name: string | null;
  line_id: number | null;
  secondary_line_id: number | null;
  product_type_id: number | null;
  status: string;
  priority: number | null;
  planned_qty: number | string | null;
  produced_qty: number | string | null;
  progress_percent: number;
  is_overdue: boolean;
  due_date: string | null;
  end_date: string | null;
  placements: PlannerPlacement[];
  week_number: number | null;
  month_number: number | null;
  shift_number: number | null;
  end_shift_number: number | null;
  planned_start_at: string | null;
  planned_end_at: string | null;
}


/** A segment key: the primary placement, or an extra placement's id. */
export type PlacementKey = 'primary' | number;

export interface Segment {
  key: PlacementKey;
  line_id: number | null;
  due_date: string | null;
  shift_number: number | null;
  end_date: string | null;
  end_shift_number: number | null;
}

/** One column of the board's date axis. */
export interface PlannerDay {
  date: string;
  isWeekend: boolean;
}

/** The neighbouring-line chip shown on a multi-line (staircase) order. */
export interface ChainChip {
  code: string;
  /** 'to' = continues there next, 'from' = came from there, 'both' = concurrent. */
  dir: 'to' | 'from' | 'both';
}

/** A placed block on the weekly board, after lane packing. */
export interface WeeklyItem {
  wo: PlannerOrder;
  placementKey: PlacementKey;
  lineId: number | null;
  startCol: number;
  endCol: number;
  lane: number;
}

export interface HourlyItem {
  wo: PlannerOrder;
  placementKey: PlacementKey;
  /** Minute-of-day, clamped to the visible day. */
  start: number;
  end: number;
  /** The order runs outside this day too — read-only here. */
  spansOutside: boolean;
  /** No exact time yet; shown as a dashed stub to drag into place. */
  placeholder: boolean;
  lane: number;
  conflict: boolean;
}

const segStartKey = (p: Segment) =>
  `${p.due_date}|${String(p.shift_number ?? 1).padStart(2, '0')}`;
const segEndKey = (p: Segment) =>
  `${p.end_date || p.due_date}|${String(p.end_shift_number ?? p.shift_number ?? 1).padStart(2, '0')}`;

/** 'YYYY-MM-DD' → local Date at noon (TZ-safe: midnight can shift a day). */
export function parseDate(s: string | null | undefined): Date | null {
  if (!s) return null;
  const [y, m, d] = s.split('-').map(Number);
  return new Date(y, m - 1, d, 12, 0, 0);
}

export function fmtKey(date: Date): string {
  return (
    date.getFullYear() +
    '-' +
    String(date.getMonth() + 1).padStart(2, '0') +
    '-' +
    String(date.getDate()).padStart(2, '0')
  );
}

export function todayKey(): string {
  return fmtKey(new Date());
}

export function dayList(startStr: string, count: number, showWeekends: boolean): PlannerDay[] {
  const start = parseDate(startStr);
  if (!start) return [];
  const out: PlannerDay[] = [];
  for (let i = 0; out.length < count && i < 90; i++) {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    const dow = d.getDay();
    if (!showWeekends && (dow === 0 || dow === 6)) continue;
    out.push({ date: fmtKey(d), isWeekend: dow === 0 || dow === 6 });
  }
  return out;
}

/**
 * Read the wall-clock time straight from the ISO string (which carries the
 * plant-timezone offset the server emitted) rather than via `new Date()`, so the
 * device's timezone can't shift the layout or the labels.
 */
export function fmtTime(iso: string | null | undefined): string {
  const m = /T(\d{2}):(\d{2})/.exec(iso ?? '');
  return m ? `${m[1]}:${m[2]}` : '';
}

export function minuteOfDay(iso: string | null | undefined): number {
  const m = /T(\d{2}):(\d{2})/.exec(iso ?? '');
  return m ? Number(m[1]) * 60 + Number(m[2]) : 0;
}

/** ISO week number for a 'YYYY-MM-DD' string. */
export function isoWeek(dateStr: string): number | null {
  const d = parseDate(dateStr);
  if (!d) return null;
  const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
  const day = t.getUTCDay() || 7;
  t.setUTCDate(t.getUTCDate() + 4 - day);
  const yearStart = new Date(Date.UTC(t.getUTCFullYear(), 0, 1));
  return Math.ceil(((t.getTime() - yearStart.getTime()) / 86400000 + 1) / 7);
}

export function placementsOf(wo: PlannerOrder): Segment[] {
  return [
    {
      key: 'primary',
      line_id: wo.line_id,
      due_date: wo.due_date,
      shift_number: wo.shift_number,
      end_date: wo.end_date,
      end_shift_number: wo.end_shift_number,
    },
    ...(wo.placements ?? []).map((p: PlannerPlacement) => ({
      key: p.id as PlacementKey,
      line_id: p.line_id,
      due_date: p.due_date,
      shift_number: p.shift_number,
      end_date: p.end_date,
      end_shift_number: p.end_shift_number,
    })),
  ];
}

/** An order occupies every line any of its segments runs on. */
export function onLine(wo: PlannerOrder, lineId: number): boolean {
  return wo.line_id === lineId || (wo.placements ?? []).some((p) => p.line_id === lineId);
}

/**
 * The order as if the given segment were its (coarse) schedule. Extra segments
 * never carry the minute plan — that belongs to the primary.
 */
export function projectSegment(wo: PlannerOrder, p: Segment): PlannerOrder {
  if (p.key === 'primary') return wo;
  return {
    ...wo,
    due_date: p.due_date,
    shift_number: p.shift_number ?? wo.shift_number,
    end_date: p.end_date,
    end_shift_number: p.end_shift_number,
    planned_start_at: null,
    planned_end_at: null,
  };
}

/**
 * The order's dated segments in chronological order — the chain a staircase
 * follows across lines (chips and connectors are drawn between neighbours).
 */
export function segmentChain(wo: PlannerOrder): Segment[] {
  return placementsOf(wo)
    .filter((p) => p.line_id && p.due_date)
    .sort((a, b) => (segStartKey(a) < segStartKey(b) ? -1 : 1));
}

export function chainChipMeta(
  wo: PlannerOrder,
  key: PlacementKey,
  allLines: { id: number; code: string | null }[],
): ChainChip | null {
  const chain = segmentChain(wo);
  if (chain.length < 2) return null;
  const idx = chain.findIndex((p) => p.key === key);
  if (idx < 0) return null;
  const codeOf = (p: Segment) => allLines.find((l) => l.id === p.line_id)?.code ?? '?';
  const next = chain[idx + 1];
  const prev = chain[idx - 1];
  if (next) return { code: codeOf(next), dir: segStartKey(next) > segEndKey(chain[idx]) ? 'to' : 'both' };
  if (prev) return { code: codeOf(prev), dir: segEndKey(prev) < segStartKey(chain[idx]) ? 'from' : 'both' };
  return null;
}

/**
 * The coarse (day + shift) slot an order occupies. Placed by due_date;
 * minute-planned orders still appear, falling back to the planned date and
 * deriving the shift from the planned hour when no explicit shift_number is set.
 */
export function weeklySlot(
  wo: PlannerOrder,
  shiftsPerDay: number,
): { date: string | null; shift: number } {
  let date = wo.due_date;
  let shift = wo.shift_number;
  if (!date && wo.planned_start_at) date = wo.planned_start_at.slice(0, 10);
  if (!shift && wo.planned_start_at) {
    const h = Math.floor(minuteOfDay(wo.planned_start_at) / 60);
    shift = Math.min(shiftsPerDay, Math.floor(h / (24 / shiftsPerDay)) + 1);
  }
  return { date, shift: Math.min(shift || 1, shiftsPerDay) };
}

/**
 * Which calendar day a work order shows on in the monthly view. Mirrors
 * weeklySlot's precedence so coarsely-placed orders aren't dropped: explicit
 * due_date, else the planned-start day, else the Monday of its ISO week, else
 * (month-only) the 1st of its month.
 */
export function onMonthlyDay(wo: PlannerOrder, iso: string, dayNum: number, monthNum: number): boolean {
  // Extra segments occupy their own days too.
  if ((wo.placements ?? []).some((p) => p.due_date === iso)) return true;
  if (wo.due_date) return wo.due_date === iso;
  if (wo.planned_start_at) return wo.planned_start_at.slice(0, 10) === iso;
  if (wo.week_number) {
    const d = parseDate(iso);
    return !!d && d.getDay() === 1 && isoWeek(iso) === wo.week_number;
  }
  if (wo.month_number) return wo.month_number === monthNum && dayNum === 1;
  return false;
}

/**
 * Lay a line's orders onto the day×shift columns as spanning blocks. Each item
 * gets startCol/endCol (covering due_date·shift → end_date·end_shift) and a lane
 * so overlapping spans stack instead of colliding.
 * Columns: day * shiftsPerDay + (shift - 1).
 */
export function weeklyPlacements(
  orders: PlannerOrder[],
  days: PlannerDay[],
  shiftsPerDay: number,
  lineId: number | null = null,
): { items: WeeklyItem[]; lanes: number; N: number } {
  const dayIdx: Record<string, number> = {};
  days.forEach((d, i) => {
    dayIdx[d.date] = i;
  });
  const N = days.length * shiftsPerDay;
  const colOf = (date: string | null, shift: number) =>
    date != null && date in dayIdx
      ? dayIdx[date] * shiftsPerDay + (Math.min(shift, shiftsPerDay) - 1)
      : -1;

  const items: WeeklyItem[] = [];
  orders.forEach((orig) => {
    // One block per segment the order runs on this line.
    const segs =
      lineId != null
        ? placementsOf(orig).filter((p) => p.line_id === lineId)
        : [placementsOf(orig)[0]];
    segs.forEach((p) => {
      const wo = projectSegment(orig, p);
      const sl = weeklySlot(wo, shiftsPerDay);
      const startCol = colOf(sl.date, sl.shift);
      if (startCol < 0) return;
      let endCol = startCol;
      if (wo.end_date && wo.end_date in dayIdx) {
        endCol = colOf(wo.end_date, wo.end_shift_number || sl.shift);
      } else if (wo.end_shift_number && wo.end_shift_number > sl.shift) {
        endCol = colOf(sl.date, wo.end_shift_number);
      }
      if (endCol < startCol) endCol = startCol;
      items.push({ wo: orig, placementKey: p.key, lineId, startCol, endCol, lane: 0 });
    });
  });

  items.sort((a, b) => a.startCol - b.startCol || b.endCol - a.endCol);
  const laneEnds: number[] = [];
  items.forEach((it) => {
    let lane = laneEnds.findIndex((e) => e <= it.startCol);
    if (lane === -1) {
      lane = laneEnds.length;
      laneEnds.push(0);
    }
    laneEnds[lane] = it.endCol + 1;
    it.lane = lane;
  });
  return { items, lanes: Math.max(1, laneEnds.length), N };
}

/** Occupancy-based load % for a line over the visible days × shifts. */
export function lineLoad(
  orders: PlannerOrder[],
  lineId: number,
  days: PlannerDay[],
  shiftsPerDay: number,
): number {
  const total = days.length * shiftsPerDay;
  if (!total) return 0;
  const dayIdx: Record<string, number> = {};
  days.forEach((d, i) => {
    dayIdx[d.date] = i;
  });
  const covered = new Set<number>();
  orders.forEach((o) => {
    placementsOf(o)
      .filter((p) => p.line_id === lineId)
      .forEach((p) => {
        const { date, shift } = weeklySlot(projectSegment(o, p), shiftsPerDay);
        if (date == null || !(date in dayIdx)) return;
        covered.add(dayIdx[date] * shiftsPerDay + (shift - 1));
      });
  });
  return Math.round((covered.size / total) * 100);
}

/** Greedy interval lane packing + conflict detection per line, for one day. */
export function hourlyLanes(
  orders: PlannerOrder[],
  lineId: number,
  dateStr: string,
): { items: HourlyItem[]; totalLanes: number } {
  const items: HourlyItem[] = orders
    // One bar per segment on this line. Extra segments are read-only coarse
    // placeholders here — the minute plan lives on the primary.
    .flatMap((orig) =>
      placementsOf(orig)
        .filter((p) => p.line_id === lineId)
        .map((p) => ({ orig, proj: projectSegment(orig, p), key: p.key })),
    )
    .filter(({ proj }) => {
      if (proj.planned_start_at && proj.planned_end_at) {
        return proj.planned_start_at.slice(0, 10) <= dateStr && dateStr <= proj.planned_end_at.slice(0, 10);
      }
      // Legacy: a due-date-only order on this day shows as a placeholder block
      // so it stays visible and can be dragged to get real times.
      return proj.due_date === dateStr;
    })
    .map(({ orig, proj, key }) => {
      if (!proj.planned_start_at || !proj.planned_end_at) {
        return {
          wo: orig,
          placementKey: key,
          start: 0,
          end: 60,
          spansOutside: false,
          placeholder: true,
          lane: 0,
          conflict: false,
        };
      }
      const startsBefore = proj.planned_start_at.slice(0, 10) < dateStr;
      const endsAfter = proj.planned_end_at.slice(0, 10) > dateStr;
      return {
        wo: orig,
        placementKey: key,
        start: startsBefore ? 0 : minuteOfDay(proj.planned_start_at),
        end: endsAfter ? 1440 : minuteOfDay(proj.planned_end_at),
        spansOutside: startsBefore || endsAfter,
        placeholder: false,
        lane: 0,
        conflict: false,
      };
    })
    .sort((a, b) => a.start - b.start || a.end - b.end);

  const laneEnds: number[] = [];
  items.forEach((it) => {
    let placed = -1;
    for (let l = 0; l < laneEnds.length; l++) {
      if (laneEnds[l] <= it.start) {
        placed = l;
        break;
      }
    }
    if (placed === -1) {
      placed = laneEnds.length;
      laneEnds.push(it.end);
    } else {
      laneEnds[placed] = it.end;
    }
    it.lane = placed;
  });

  items.forEach((a) => {
    a.conflict = items.some((b) => b !== a && a.start < b.end && b.start < a.end);
  });

  return { items, totalLanes: Math.max(1, laneEnds.length) };
}
