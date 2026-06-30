// Tachograph-style employee activity API client.
// Mirrors backend at /api/v1/employee-activities, /workers/{id}/day-plan, etc.

import { api } from './client';
import type { ApiEnvelope, ApiPaginated } from '@/types/api';

// ── Types ────────────────────────────────────────────────────────────────────

export type ActivityType =
  | 'work'
  | 'break'
  | 'rest'
  | 'travel'
  | 'setup'
  | 'meeting'
  | 'training'
  | 'maint'
  | 'qc'
  | 'off'
  | 'custom';

export interface ActivityTypeMeta {
  color: string;
  short: string;
  label: string;
}

export type TypeMetaMap = Record<ActivityType, ActivityTypeMeta>;

export interface ActivityTypeCatalogEntry {
  key: string;
  code?: string;
  label: string;
  short: string;
  color: string;
  icon?: string | null;
  custom: boolean;
}

export interface EmployeeActivity {
  id: number;
  worker_id: number;
  worker?: { id: number; code: string; name: string } | null;
  type: ActivityType;
  custom_code?: string | null;
  label?: string | null;
  starts_at: string;
  ends_at: string;
  from?: string;
  to?: string;
  duration_min: number;
  work_order_id?: number | null;
  work_order?: { id: number; order_no: string } | null;
  line_id?: number | null;
  line?: { id: number; name: string } | null;
  step_name?: string | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

/** A single segment on a tachograph timeline — either a real activity or a
 *  gap-filled "off" block (id=null). Times come pre-formatted as "HH:mm". */
export interface DaySegment {
  id: number | null;
  type: ActivityType;
  custom_code?: string | null;
  label?: string | null;
  from: string;
  to: string;
  duration_min: number;
  work_order?: { id: number; order_no: string } | null;
  line?: { id: number; name: string } | null;
  step_name?: string | null;
  notes?: string | null;
}

export interface DayPlan {
  worker: {
    id: number;
    code: string;
    name: string;
    personnel_class?: { id: number; code: string; name: string } | null;
  };
  date: string;
  segments: DaySegment[];
  summary: Partial<Record<ActivityType, number>>;
  type_meta: TypeMetaMap;
}

export interface MonthPlanDay {
  date: string;
  in_month: boolean;
  is_today: boolean;
  segments: DaySegment[];
  on_duty: number;
  productive: number;
}

export interface MonthPlan {
  worker: { id: number; code: string; name: string };
  month: string;
  month_start: string;
  month_end: string;
  days: MonthPlanDay[];
  type_meta: TypeMetaMap;
}

export interface TeamDayRow {
  worker: {
    id: number;
    code: string;
    name: string;
    personnel_class?: { id: number; code: string; name: string } | null;
  };
  segments: DaySegment[];
  summary: Partial<Record<ActivityType, number>>;
  on_duty: number;
}

export interface TeamDay {
  date: string;
  rows: TeamDayRow[];
  type_meta: TypeMetaMap;
}

export interface ActivityTypeCatalog {
  built_in: ActivityTypeCatalogEntry[];
  custom: ActivityTypeCatalogEntry[];
}

export interface EmployeeActivityCustomType {
  id: number;
  code: string;
  label: string;
  color: string;
  icon?: string | null;
  is_active: boolean;
}

// ── Input shapes ─────────────────────────────────────────────────────────────

export interface CreateActivityInput {
  worker_id: number;
  type: ActivityType;
  custom_code?: string | null;
  label?: string | null;
  /** ISO 8601 ("YYYY-MM-DD HH:mm:ss" also accepted by Laravel). */
  starts_at: string;
  ends_at: string;
  work_order_id?: number | null;
  line_id?: number | null;
  step_name?: string | null;
  notes?: string | null;
}

export type UpdateActivityInput = Partial<CreateActivityInput>;

export interface ListActivitiesParams {
  worker_id?: number;
  type?: ActivityType;
  /** YYYY-MM-DD — pulls a single day. Overrides from/to. */
  date?: string;
  from?: string;
  to?: string;
  per_page?: number;
  page?: number;
}

// ── Endpoints ────────────────────────────────────────────────────────────────

export async function listActivities(params: ListActivitiesParams = {}) {
  const res = await api.get<ApiPaginated<EmployeeActivity>>('/api/v1/employee-activities', { params });
  return res.data;
}

export async function showActivity(id: number) {
  const res = await api.get<ApiEnvelope<EmployeeActivity>>(`/api/v1/employee-activities/${id}`);
  return res.data.data;
}

export async function createActivity(input: CreateActivityInput) {
  const res = await api.post<ApiEnvelope<EmployeeActivity>>('/api/v1/employee-activities', input);
  return res.data.data;
}

export async function updateActivity(id: number, input: UpdateActivityInput) {
  const res = await api.patch<ApiEnvelope<EmployeeActivity>>(`/api/v1/employee-activities/${id}`, input);
  return res.data.data;
}

export async function deleteActivity(id: number) {
  await api.delete(`/api/v1/employee-activities/${id}`);
}

/** Gap-filled 24h timeline for one worker on `date` (YYYY-MM-DD, defaults today). */
export async function fetchDayPlan(workerId: number, date?: string) {
  const res = await api.get<ApiEnvelope<DayPlan>>(`/api/v1/workers/${workerId}/day-plan`, {
    params: date ? { date } : undefined,
  });
  return res.data.data;
}

/** Calendar grid (Mon→Sun, full weeks covering the month) with per-day segments. */
export async function fetchMonthPlan(workerId: number, month?: string) {
  const res = await api.get<ApiEnvelope<MonthPlan>>(`/api/v1/workers/${workerId}/month-plan`, {
    params: month ? { month } : undefined,
  });
  return res.data.data;
}

/** All active workers stacked for `date`. */
export async function fetchTeamDay(date?: string) {
  const res = await api.get<ApiEnvelope<TeamDay>>('/api/v1/employee-activities/team-day', {
    params: date ? { date } : undefined,
  });
  return res.data.data;
}

export async function fetchActivityTypes() {
  const res = await api.get<ApiEnvelope<ActivityTypeCatalog>>('/api/v1/employee-activities/types');
  return res.data.data;
}

export async function createCustomType(input: {
  code: string;
  label: string;
  color?: string;
  icon?: string;
}) {
  const res = await api.post<ApiEnvelope<EmployeeActivityCustomType>>(
    '/api/v1/employee-activity-custom-types',
    input,
  );
  return res.data.data;
}

export async function updateCustomType(id: number, input: Partial<EmployeeActivityCustomType>) {
  const res = await api.patch<ApiEnvelope<EmployeeActivityCustomType>>(
    `/api/v1/employee-activity-custom-types/${id}`,
    input,
  );
  return res.data.data;
}

export async function deleteCustomType(id: number) {
  await api.delete(`/api/v1/employee-activity-custom-types/${id}`);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/** "HH:mm" → minutes since 00:00. */
export function toMinutes(hhmm: string): number {
  const [h, m] = hhmm.split(':').map(Number);
  return h * 60 + (m ?? 0);
}

/** minutes → "HH:mm" (zero-padded). */
export function formatMinutes(minutes: number): string {
  const m = Math.max(0, Math.round(minutes));
  return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
}

/** Productive = work + setup + qc (matches the tablet design's PRODUCTIVE KPI). */
export function productiveMinutes(summary: Partial<Record<ActivityType, number>>): number {
  return (summary.work ?? 0) + (summary.setup ?? 0) + (summary.qc ?? 0);
}

/** On-duty types include productive + maint + meeting + training. */
export const ON_DUTY_TYPES: ActivityType[] = [
  'work', 'setup', 'qc', 'maint', 'training', 'meeting',
];

export function onDutyMinutes(summary: Partial<Record<ActivityType, number>>): number {
  return ON_DUTY_TYPES.reduce((sum, t) => sum + (summary[t] ?? 0), 0);
}
