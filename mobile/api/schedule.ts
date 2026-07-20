// Schedule planner — board read plus minute-level move / resize of work orders.
// Backend at GET /api/v1/schedule/board, PUT /api/v1/schedule/{workOrder} and
// /resize, GET /changes, POST /changes/{change}/undo. All of it routes through
// the same SchedulePlannerService the web planner uses, so edits made here land
// in the audit log and stay undoable from either surface.

import type { ApiEnvelope, WorkOrder } from '@/types/api';
import { api } from './client';

/**
 * One coarse extra segment: the same order continuing on another line. Only the
 * primary placement carries the minute plan — segments are day+shift only.
 */
export interface ExtraPlacementInput {
  /** Omit to create; pass the existing id to update it in place. */
  id?: number | null;
  line_id: number;
  due_date: string;
  shift_number?: number | null;
  end_date?: string | null;
  end_shift_number?: number | null;
}

/**
 * Update one or more planner fields on a work order.
 *
 * Presence is what counts: the server only writes the keys you send, so omit a
 * field to leave it untouched rather than sending null (which clears it).
 */
export interface ScheduleUpdateInput {
  line_id?: number | null;
  due_date?: string | null;
  end_date?: string | null;
  week_number?: number | null;
  shift_number?: number | null;
  end_shift_number?: number | null;
  /** ISO 8601 (minute precision OK). */
  planned_start_at?: string | null;
  /** ISO 8601. Must be strictly after planned_start_at. */
  planned_end_at?: string | null;
  /**
   * Full-list sync of the order's extra segments: rows with an id are updated,
   * rows without one are created, and any existing row you omit is deleted.
   * Sending `line_id: null` clears every segment regardless.
   */
  extra_placements?: ExtraPlacementInput[];
  /** Skip the same-line overlap check. Use only when the caller already
   * confirmed the conflict with the user. */
  force_conflict?: boolean;
}

/** Specifically for the resize action — both timestamps required. */
export interface ScheduleResizeInput {
  planned_start_at: string;
  planned_end_at: string;
  force_conflict?: boolean;
}

/**
 * Error thrown on a 409 conflict response so call sites can prompt the user
 * and retry with `force_conflict: true`. Other errors fall through unchanged.
 */
export class ScheduleConflictError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ScheduleConflictError';
  }
}

async function unwrapConflict<T>(p: Promise<T>): Promise<T> {
  try {
    return await p;
  } catch (e) {
    const err = e as { response?: { status?: number; data?: { conflict?: boolean; message?: string } } };
    if (err?.response?.status === 409 && err.response.data?.conflict) {
      throw new ScheduleConflictError(err.response.data.message ?? 'Time slot conflict');
    }
    throw e;
  }
}

export const updateScheduleOrder = (
  workOrderId: number,
  input: ScheduleUpdateInput,
): Promise<WorkOrder> =>
  unwrapConflict(
    api
      .put<ApiEnvelope<WorkOrder>>(`/api/v1/schedule/${workOrderId}`, input)
      .then((r) => r.data.data),
  );

export const resizeScheduleOrder = (
  workOrderId: number,
  input: ScheduleResizeInput,
): Promise<WorkOrder> =>
  unwrapConflict(
    api
      .put<ApiEnvelope<WorkOrder>>(`/api/v1/schedule/${workOrderId}/resize`, input)
      .then((r) => r.data.data),
  );

// ── Board ──────────────────────────────────────────────────────────────────

export type PlannerViewMode = 'weekly' | 'daily' | 'hourly' | 'monthly';

export interface PlannerLine {
  id: number;
  name: string;
  code: string | null;
}

export interface PlannerShift {
  id: number;
  name: string;
  sort_order: number;
  start_time: string;
  end_time: string;
}

/** An extra segment as the board ships it (always has a server id). */
export interface PlannerPlacement {
  id: number;
  line_id: number;
  due_date: string;
  shift_number: number | null;
  end_date: string | null;
  end_shift_number: number | null;
}

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

export interface PlannerBacklogOrder {
  id: number;
  order_no: string;
  product_name: string | null;
  customer_name: string | null;
  customer_tier: string | null;
  line_id: number | null;
  due_date: string | null;
  planned_qty: number | string | null;
  status: string;
  priority: number | null;
  priority_score: number | null;
}

/**
 * A maintenance window on a line. The board also ships *virtual* occurrences
 * projected from recurring schedules, which have no persisted event id.
 */
export interface PlannerMaintenanceEvent {
  id: number | null;
  title: string;
  event_type: string | null;
  status: string;
  line_id: number | null;
  workstation_id: number | null;
  schedule_id: number | null;
  scheduled_at_date: string | null;
  scheduled_at_time: string | null;
  scheduled_at_minute: number | null;
  duration_minutes: number;
  description: string | null;
}

export interface PlannerBoard {
  workOrders: PlannerOrder[];
  lines: PlannerLine[];
  allLines: PlannerLine[];
  shifts: PlannerShift[];
  viewMode: PlannerViewMode;
  shiftsPerDay: number;
  slotMinutes: number;
  horizonWeeks: number;
  showWeekends: boolean;
  startDate: string;
  rangeStart: string;
  rangeEnd: string;
  navPrev: string;
  navNext: string;
  backlogOrders: PlannerBacklogOrder[];
  maintenanceEvents: PlannerMaintenanceEvent[];
  realtimeMode: string;
  overdueImportant: {
    count: number;
    orders: {
      id: number;
      order_no: string;
      customer_name: string | null;
      tier: string | null;
      due_date: string | null;
    }[];
  };
}

export interface PlannerBoardParams {
  view_mode?: PlannerViewMode;
  start_date?: string;
  line_id?: number | null;
}

export const fetchPlannerBoard = (params: PlannerBoardParams = {}): Promise<PlannerBoard> =>
  api
    .get<ApiEnvelope<PlannerBoard>>('/api/v1/schedule/board', { params })
    .then((r) => r.data.data);

// ── Changes + undo ─────────────────────────────────────────────────────────

/** One restorable placement snapshot, as stored in the audit log. */
export interface PlacementSnapshot {
  line_id: number | null;
  due_date: string | null;
  week_number: number | null;
  shift_number: number | null;
  end_date: string | null;
  end_shift_number: number | null;
  planned_start_at: string | null;
  planned_end_at: string | null;
  placements: Omit<PlannerPlacement, 'id'>[];
}

export interface ScheduleChange {
  id: number;
  work_order_id: number;
  order_no: string | null;
  /** 'reschedule' for an edit, 'undo' for a revert (itself undoable). */
  action: string;
  before: PlacementSnapshot | null;
  after: PlacementSnapshot | null;
  user: string | null;
  undone_at: string | null;
  created_at: string;
}

export const fetchScheduleChanges = (): Promise<ScheduleChange[]> =>
  api.get<ApiEnvelope<ScheduleChange[]>>('/api/v1/schedule/changes').then((r) => r.data.data);

export const undoScheduleChange = (changeId: number): Promise<void> =>
  api.post(`/api/v1/schedule/changes/${changeId}/undo`).then(() => undefined);
