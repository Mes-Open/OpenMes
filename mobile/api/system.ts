import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface SystemSetting {
  key: string;
  value: unknown;
  description?: string | null;
  updated_at?: string;
}

export interface ModuleManifest {
  name: string;
  display_name: string;
  description?: string;
  version?: string;
  enabled: boolean;
  has_error?: boolean;
  directory?: string;
}

export interface DashboardWidget {
  id: number;
  widget_id: string;
  name: string;
  zone: string | null;
  description: string | null;
  source: string | null;
  module_name: string | null;
  enabled: boolean;
  sort_order: number;
  config: Record<string, unknown> | null;
}

export const listDashboardWidgets = (): Promise<DashboardWidget[]> =>
  api
    .get<ApiEnvelope<DashboardWidget[]>>('/api/v1/system/dashboard-widgets')
    .then((r) => r.data.data);

export interface ScheduleEvent {
  type: 'maintenance' | 'work_order';
  id: number;
  /** Backend now returns line_id for both maintenance + work_order events. */
  line_id?: number | null;
  title: string;
  starts_at?: string | null;
  /** Work orders still return null — derived from due_date with no scheduled
   * end yet. Maintenance returns completed_at when finished. */
  ends_at?: string | null;
  status: string;
  color: string;
}

/** Status used by Gantt blocks — maps to a colored bar. */
export type MockBlockStatus =
  | 'running'
  | 'queued'
  | 'paused'
  | 'blocked'
  | 'maintenance';

export interface MockGanttBlock {
  id: string;
  /** Display label inside the block (e.g. work order number). */
  title: string;
  /**
   * Absolute hour the block starts at, in the day [0, 24]. Fractional values
   * are allowed (e.g. 6.4 = 06:24).
   */
  startHour: number;
  /** Block duration in hours. Fractional values are allowed. */
  durationHours: number;
  status: MockBlockStatus;
  kind: 'work_order' | 'maintenance';
  /** Carried through so the planner can open an edit modal on the WO. Null
   * for maintenance blocks (those go through the events screen). */
  workOrderId?: number | null;
  /** The line this block lives on — needed to default the line picker. */
  lineId?: number | null;
  /** Original planned timestamps in ISO 8601, used as defaults in the edit
   * modal. The numeric `startHour`/`durationHours` above are derived from
   * these but lose the date, so we keep the raw strings too. */
  plannedStartAt?: string | null;
  plannedEndAt?: string | null;
}

export interface OperatorOnShift {
  id: number;
  code: string;
  name: string;
  crew?: string | null;
  line_id?: number | null;
  line_code?: string | null;
  line_name?: string | null;
  status: 'in' | 'break' | 'no-show' | 'out';
}

export type AlertType =
  | 'issue'
  | 'maintenance'
  | 'machine_offline'
  | 'overdue_order'
  | 'blocked_order'
  | 'blocking_issue';

export type AlertSeverity = 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';

export interface Alert {
  type: AlertType;
  id: number;
  title: string;
  severity: AlertSeverity | string;
  status: string;
  created_at?: string | null;
  link: string;
}

export interface AlertCounts {
  issues: number;
  maintenance: number;
  machines: number;
  overdue_orders: number;
  blocked_orders: number;
  blocking_issues: number;
  total: number;
}

export interface UpdateCheckResponse {
  current_version: string;
  latest_version: string;
  update_available: boolean;
  release_notes_url?: string | null;
}

// Settings
export const listSettings = (): Promise<SystemSetting[]> =>
  api.get<ApiEnvelope<SystemSetting[]>>('/api/v1/system/settings').then((r) => r.data.data);

export const getSetting = (key: string): Promise<SystemSetting> =>
  api
    .get<ApiEnvelope<SystemSetting>>(`/api/v1/system/settings/${encodeURIComponent(key)}`)
    .then((r) => r.data.data);

export const updateSetting = (key: string, value: unknown): Promise<SystemSetting> =>
  api
    .put<ApiEnvelope<SystemSetting>>(
      `/api/v1/system/settings/${encodeURIComponent(key)}`,
      { value },
    )
    .then((r) => r.data.data);

// Modules
export const listModules = (): Promise<ModuleManifest[]> =>
  api.get<ApiEnvelope<ModuleManifest[]>>('/api/v1/system/modules').then((r) => r.data.data);

export const enableModule = (name: string): Promise<void> =>
  api
    .post(`/api/v1/system/modules/${encodeURIComponent(name)}/enable`)
    .then(() => undefined);

export const disableModule = (name: string): Promise<void> =>
  api
    .post(`/api/v1/system/modules/${encodeURIComponent(name)}/disable`)
    .then(() => undefined);

// Schedule — /api/v1/system/schedule returns events bounded by [from, to],
// each with line_id, status, color. The Gantt buckets these by line client-
// side; the flat events panel renders them as a list.
export const listScheduleEvents = (opts: {
  from: string;
  to: string;
  line_id?: number;
  type?: 'maintenance' | 'work_order' | 'all';
}): Promise<ScheduleEvent[]> =>
  api
    .get<ApiEnvelope<ScheduleEvent[]>>('/api/v1/system/schedule', { params: opts })
    .then((r) => r.data.data);

/**
 * Filters/clips a full-day block list to a visible hour window. Blocks that
 * fall entirely outside are dropped; blocks that straddle a boundary are
 * clipped so the rendered bar stays inside the window.
 */
export function clipBlocksToWindow(
  blocks: MockGanttBlock[],
  windowStart: number,
  windowEnd: number,
): MockGanttBlock[] {
  return blocks.flatMap((b) => {
    const end = b.startHour + b.durationHours;
    if (end <= windowStart) return [];
    if (b.startHour >= windowEnd) return [];
    const clippedStart = Math.max(b.startHour, windowStart);
    const clippedEnd = Math.min(end, windowEnd);
    return [{ ...b, startHour: clippedStart, durationHours: clippedEnd - clippedStart }];
  });
}

// Operators on shift — backend doesn't ship this endpoint yet. Returns []
// until /api/v1/system/operators-on-shift lands; the schedule screen's
// existing empty state covers the absence.
export const listOperatorsOnShift = async (
  _date?: string,
  _workers: Array<{
    id: number;
    name: string;
    code: string;
    crew?: { name?: string | null } | null;
    is_active?: boolean;
  }> = [],
  _lines: Array<{ id: number; code?: string | null; name: string }> = [],
): Promise<OperatorOnShift[]> => [];

// Alerts
export const listAlerts = (
  type: 'all' | AlertType = 'all',
): Promise<Alert[]> =>
  api
    .get<ApiEnvelope<Alert[]>>('/api/v1/system/alerts', { params: { type } })
    .then((r) => r.data.data);

export const getAlertCounts = (): Promise<AlertCounts> =>
  api.get<ApiEnvelope<AlertCounts>>('/api/v1/system/alerts/counts').then((r) => r.data.data);

// Update Check
export const checkForUpdates = (): Promise<UpdateCheckResponse> =>
  api
    .get<ApiEnvelope<UpdateCheckResponse>>('/api/v1/system/update-check')
    .then((r) => r.data.data);
