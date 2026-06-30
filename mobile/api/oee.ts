import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface OeeRecord {
  id: number;
  line_id: number;
  workstation_id?: number | null;
  shift_id?: number | null;
  record_date: string; // ISO date
  planned_minutes: number;
  operating_minutes: number;
  downtime_minutes: number;
  ideal_cycle_minutes?: number | string | null;
  total_produced: number | string;
  good_produced: number | string;
  scrap_qty: number | string;
  availability_pct?: number | string | null;
  performance_pct?: number | string | null;
  quality_pct?: number | string | null;
  oee_pct?: number | string | null;
  line?: { id: number; name: string; code?: string | null };
  shift?: { id: number; name?: string | null };
}

export interface OeeFilters {
  line_id?: number;
  date_from?: string;
  date_to?: string;
}

export const listOee = (filters: OeeFilters = {}): Promise<OeeRecord[]> =>
  api
    .get<ApiEnvelope<OeeRecord[]>>('/api/v1/oee', { params: filters })
    .then((r) => r.data.data);

export const getOeeForLine = (lineId: number, days = 7): Promise<OeeRecord[]> =>
  api
    .get<ApiEnvelope<OeeRecord[]>>(`/api/v1/oee/${lineId}`, { params: { days } })
    .then((r) => r.data.data);
