import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface ShiftBalance {
  line_id: number | null;
  shift_id: number | null;
  window: { start: string; end: string; business_date: string };
  /** The matched shift (name/code + HH:mm window), or null when none is configured. */
  shift?: { name: string; code?: string | null; start: string; end: string } | null;
  produced_qty: number;
  scrap_qty: number;
  good_qty: number;
  packed_qty: number;
  wip_open_pallets_qty: number;
  wip_open_pallets_count: number;
  wip_unpacked_qty: number;
  wip_total_qty: number;
  shipped_qty: number;
  discrepancies: Array<{ label: string; value: number; severity: string }>;
  open_pallets: Array<{ id: number; pallet_no?: string | null; order_no?: string | null; qty: number }>;
}

export interface RecentHandover {
  id: number;
  business_date?: string | null;
  shift_start?: string | null;
  line_name?: string | null;
  produced_qty: number;
  good_qty: number;
  packed_qty: number;
  shipped_qty: number;
  confirmed_by?: string | null;
  confirmed_at?: string | null;
}

export interface ShiftHandoverData {
  lines: Array<{ id: number; name: string; code?: string | null }>;
  selected_line_id: number | null;
  balance: ShiftBalance;
  recent: RecentHandover[];
}

/** GET /api/v1/shift-handover — live shift balance + active lines + recent snapshots. */
export const getShiftHandover = (lineId?: number | null): Promise<ShiftHandoverData> =>
  api
    .get<ApiEnvelope<ShiftHandoverData>>('/api/v1/shift-handover', {
      params: { line_id: lineId ?? undefined },
    })
    .then((r) => r.data.data);

/** POST /api/v1/shift-handover — close the shift (server snapshots the balance). */
export const closeShift = (payload: { line_id?: number | null; notes?: string }): Promise<unknown> =>
  api.post('/api/v1/shift-handover', payload).then((r) => r.data);
