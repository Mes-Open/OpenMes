// Schedule capacity — available vs planned hours per resource per time bucket.
// Backend at GET /api/v1/schedule/capacity (read-only; Admin|Supervisor).

import { api } from './client';

export type CapacityAxis = 'line' | 'crew';
export type CapacityGranularity = 'week' | 'day';

export interface CapacityBucket {
  key: string;
  label: string;
  start: string;
  end: string;
}

export interface CapacityCell {
  available_h: number;
  planned_h: number;
  load_pct: number | null;
  unestimated_count: number;
}

export interface CapacityResource {
  id: number;
  name: string;
  code: string | null;
  cells: Record<string, CapacityCell | undefined>;
}

export interface CapacityGrid {
  granularity: CapacityGranularity;
  buckets: CapacityBucket[];
  resources: CapacityResource[];
}

export interface CapacityResponse {
  grid: CapacityGrid;
  granularity: CapacityGranularity;
  axis: CapacityAxis;
  range_start: string;
  range_end: string;
  nav_prev: string;
  nav_next: string;
}

export interface CapacityParams {
  axis?: CapacityAxis;
  granularity?: CapacityGranularity;
  /** Anchor date (YYYY-MM-DD); the window is derived from it. */
  start_date?: string;
}

export const getCapacityGrid = (params: CapacityParams = {}): Promise<CapacityResponse> =>
  api
    .get<CapacityResponse>('/api/v1/schedule/capacity', { params })
    .then((r) => r.data);
