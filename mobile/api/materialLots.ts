import { api } from './client';
import type { ApiEnvelope, ApiPaginated } from '@/types/api';

export type MaterialLotStatus =
  | 'pending_inspection'
  | 'available'
  | 'quarantined'
  | 'consumed'
  | 'scrapped'
  | 'expired';

export interface MaterialLot {
  id: number;
  lot_number: string;
  material_id: number;
  source_id?: number | null;
  quantity_received: string | number;
  quantity_available: string | number;
  unit_of_measure: string;
  received_at: string;
  manufacturing_date?: string | null;
  expiry_date?: string | null;
  status: MaterialLotStatus | string;
  supplier_lot_no?: string | null;
  supplier_reference?: string | null;
  inspection_id?: number | null;
  created_by_id?: number;
  tenant_id?: number;
  extra_data?: Record<string, unknown> | null;
  created_at?: string;
  updated_at?: string;
  material?: { id: number; code?: string; name?: string };
  source?: { id: number; name?: string; code?: string } | null;
  inspection?: { id: number; status?: string } | null;
  sublots?: MaterialSublot[];
  createdBy?: { id: number; name?: string; username?: string } | null;
}

export interface MaterialSublot {
  id: number;
  material_lot_id: number;
  sublot_number?: string | null;
  quantity?: string | number;
  unit_of_measure?: string;
  status?: string;
  created_at?: string;
}

export interface BatchStepLotConsumption {
  id: number;
  material_lot_id: number;
  batch_step_id: number;
  sublot_id?: number | null;
  quantity: string | number;
  unit_of_measure?: string;
  recorded_at?: string;
  recorded_by_id?: number | null;
  materialLot?: MaterialLot;
  batchStep?: {
    id: number;
    batch?: {
      id: number;
      batch_no?: string;
      work_order?: {
        id: number;
        order_no?: string;
        product_type?: { id: number; name: string };
      };
    };
  };
  sublot?: MaterialSublot | null;
  recordedBy?: { id: number; name?: string; username?: string } | null;
}

export interface RecallImpactWorkOrder {
  id: number;
  order_no: string;
  product?: string | null;
  status: string;
  quantity_consumed: number;
  batches: number[];
  finished_serials: Array<{ serial_no: string; status: string; produced_at?: string | null }>;
}

export interface RecallImpactPayload {
  source_lots: Array<{ lot_number: string; material?: string | null; supplier_lot_no?: string | null }>;
  work_orders: RecallImpactWorkOrder[];
  truncated: boolean;
  totals: { work_orders: number; finished_serials: number; quantity_consumed: number };
}

export interface ForwardGenealogyPayload {
  lot: { id: number; lot_number: string; material_id: number; status: string };
  consumptions: BatchStepLotConsumption[];
  total_consumed: number;
  consumed_in_steps: number[];
  recall?: RecallImpactPayload | null;
}

export interface BackwardGenealogyPayload {
  lot: { id: number; lot_number: string; material_id: number; status: string };
  inspection?: { id: number; status?: string } | null;
  supplier_lot_no?: string | null;
  supplier_reference?: string | null;
  source_batch_id?: number | null;
  upstream_consumptions: BatchStepLotConsumption[];
}

export interface MaterialLotFilters {
  material_id?: number;
  status?: MaterialLotStatus | string;
  supplier_lot_no?: string;
  expiring_within_days?: number;
  available_only?: boolean;
  per_page?: number;
  page?: number;
}

export interface ConsumeLotPayload {
  batch_step_id: number;
  quantity: number;
  sublot_id?: number;
}

export interface ConsumeLotResponse {
  consumption: BatchStepLotConsumption;
  lot: MaterialLot;
}

export const listMaterialLots = (
  opts: MaterialLotFilters = {},
): Promise<ApiPaginated<MaterialLot>> =>
  api
    .get<ApiPaginated<MaterialLot>>('/api/v1/material-lots', { params: opts })
    .then((r) => r.data);

export const getMaterialLot = (id: number): Promise<MaterialLot> =>
  api
    .get<ApiEnvelope<MaterialLot>>(`/api/v1/material-lots/${id}`)
    .then((r) => r.data.data);

export const getLotForwardGenealogy = (
  id: number,
): Promise<ForwardGenealogyPayload> =>
  api
    .get<ApiEnvelope<ForwardGenealogyPayload>>(
      `/api/v1/material-lots/${id}/genealogy/forward`,
    )
    .then((r) => r.data.data);

export const getLotBackwardGenealogy = (
  id: number,
): Promise<BackwardGenealogyPayload> =>
  api
    .get<ApiEnvelope<BackwardGenealogyPayload>>(
      `/api/v1/material-lots/${id}/genealogy/backward`,
    )
    .then((r) => r.data.data);

export const consumeMaterialLot = (
  id: number,
  payload: ConsumeLotPayload,
): Promise<ConsumeLotResponse> =>
  api
    .post<ApiEnvelope<ConsumeLotResponse>>(
      `/api/v1/material-lots/${id}/consume`,
      payload,
    )
    .then((r) => r.data.data);
