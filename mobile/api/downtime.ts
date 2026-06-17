import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export type DowntimeKind = 'planned' | 'unplanned' | 'changeover';

/** Only `unplanned` + `changeover` count as availability loss for OEE.
 *  Mirrors backend `App\Enums\DowntimeKind::countsAsAvailabilityLoss()`. */
export const DOWNTIME_LOSS_KINDS: readonly DowntimeKind[] = ['unplanned', 'changeover'];

export interface DowntimeReason {
  id: number;
  code: string;
  name: string;
  /** Replaces legacy `is_planned` bool. Migration:
   *  `replace_is_planned_with_kind_on_downtime_reasons` (2026-05-19). */
  kind: DowntimeKind;
  is_active: boolean;
}

export interface ProductionDowntime {
  id: number;
  line_id: number;
  workstation_id?: number | null;
  downtime_reason_id: number;
  shift_id?: number | null;
  started_at: string;
  ended_at?: string | null;
  duration_minutes?: number | null;
  notes?: string | null;
  reported_by?: number | null;
  reason?: DowntimeReason | null;
  line?: { id: number; name: string };
  workstation?: { id: number; name: string } | null;
  reported_by_user?: { id: number; name?: string | null; username: string } | null;
}

export interface DowntimeFilters {
  line_id?: number;
  date?: string;
}

export interface StartDowntimeInput {
  line_id: number;
  workstation_id?: number | null;
  downtime_reason_id: number;
  notes?: string;
}

export const listDowntimeReasons = (): Promise<DowntimeReason[]> =>
  api
    .get<ApiEnvelope<DowntimeReason[]>>('/api/v1/downtime-reasons')
    .then((r) => r.data.data);

export const listDowntimes = (filters: DowntimeFilters = {}): Promise<ProductionDowntime[]> =>
  api
    .get<ApiEnvelope<ProductionDowntime[]>>('/api/v1/downtimes', { params: filters })
    .then((r) => r.data.data);

export const startDowntime = (input: StartDowntimeInput): Promise<ProductionDowntime> =>
  api
    .post<ApiEnvelope<ProductionDowntime>>('/api/v1/downtimes', input)
    .then((r) => r.data.data);

export const stopDowntime = (id: number): Promise<ProductionDowntime> =>
  api
    .patch<ApiEnvelope<ProductionDowntime>>(`/api/v1/downtimes/${id}`)
    .then((r) => r.data.data);
