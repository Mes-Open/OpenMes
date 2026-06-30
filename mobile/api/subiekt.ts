import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface SubiektStatus {
  health: { status: string; [key: string]: unknown };
  connection: {
    connected: boolean;
    endpoint?: string | null;
    last_sync_at?: string | null;
    [key: string]: unknown;
  };
}

export interface SubiektProduct {
  symbol: string;
  name: string;
  unit?: string | null;
  stock?: number | null;
  vat_rate?: number | null;
  price_net?: number | null;
  warehouse?: string | null;
  [key: string]: unknown;
}

export interface SubiektContractor {
  symbol: string;
  name: string;
  nip?: string | null;
  city?: string | null;
}

export interface SubiektWarehouse {
  symbol: string;
  name: string;
}

export interface SubiektStock {
  symbol: string;
  name: string;
  warehouse: string;
  quantity: number;
}

export const getSubiektStatus = (): Promise<SubiektStatus> =>
  api.get<ApiEnvelope<SubiektStatus>>('/api/v1/subiekt/status').then((r) => r.data.data);

export const connectSubiekt = (): Promise<{ connected: boolean }> =>
  api
    .post<ApiEnvelope<{ connected: boolean }>>('/api/v1/subiekt/connect')
    .then((r) => r.data.data);

export const listSubiektProducts = (limit = 50): Promise<SubiektProduct[]> =>
  api
    .get<ApiEnvelope<SubiektProduct[]>>('/api/v1/subiekt/products', { params: { limit } })
    .then((r) => r.data.data);

export const listSubiektContractors = (): Promise<SubiektContractor[]> =>
  api.get<ApiEnvelope<SubiektContractor[]>>('/api/v1/subiekt/contractors').then((r) => r.data.data);

export const listSubiektWarehouses = (): Promise<SubiektWarehouse[]> =>
  api.get<ApiEnvelope<SubiektWarehouse[]>>('/api/v1/subiekt/warehouses').then((r) => r.data.data);

export const listSubiektStock = (): Promise<SubiektStock[]> =>
  api.get<ApiEnvelope<SubiektStock[]>>('/api/v1/subiekt/stock').then((r) => r.data.data);

export const syncSubiekt = (): Promise<unknown> =>
  api.post<ApiEnvelope<unknown>>('/api/v1/subiekt/sync').then((r) => r.data.data);
