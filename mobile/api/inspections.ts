import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export type InspectionCriterionType =
  | 'visual'
  | 'measurement'
  | 'functional'
  | 'pass_fail';

export type InspectionStatus =
  | 'pending'
  | 'pass'
  | 'fail'
  | 'conditional_pass';

export type InspectionDisposition =
  | 'pending'
  | 'released'
  | 'quarantined'
  | 'scrapped'
  | 'rework';

export interface InspectionCriterion {
  name: string;
  type: InspectionCriterionType | string;
  required?: boolean;
  unit?: string | null;
  spec_min?: number | string | null;
  spec_max?: number | string | null;
}

export interface InspectionPlan {
  id: number;
  name: string;
  description?: string | null;
  material_id?: number | null;
  material_type_id?: number | null;
  criteria: InspectionCriterion[];
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
  material?: { id: number; code?: string; name?: string } | null;
  materialType?: { id: number; name: string } | null;
}

export interface InspectionResult {
  id: number;
  inspection_id: number;
  criterion_name: string;
  criterion_type: InspectionCriterionType | string;
  required: boolean;
  unit?: string | null;
  spec_min?: string | number | null;
  spec_max?: string | number | null;
  value_numeric?: string | number | null;
  value_boolean?: boolean | null;
  value_text?: string | null;
  is_passed?: boolean | null;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface Inspection {
  id: number;
  inspection_plan_id?: number | null;
  material_id: number;
  lot_number: string;
  supplier_lot_ref?: string | null;
  quantity_received?: string | number | null;
  inspector_id: number;
  started_at: string;
  completed_at?: string | null;
  status: InspectionStatus | string;
  notes?: string | null;
  issue_id?: number | null;
  tenant_id?: number;
  disposition: InspectionDisposition | string;
  disposition_notes?: string | null;
  disposition_by_id?: number | null;
  disposition_at?: string | null;
  created_at?: string;
  updated_at?: string;
  material?: { id: number; code?: string; name?: string };
  plan?: InspectionPlan | null;
  inspector?: { id: number; name?: string; username?: string };
  issue?: { id: number; title?: string } | null;
  results?: InspectionResult[];
}

export interface InspectionStats {
  window_days: number;
  total_completed: number;
  pass_count: number;
  fail_count: number;
  conditional_pass_count: number;
  pending_count: number;
  pass_rate: number | null;
}

// ── Inspection plans ────────────────────────────────────────────────────────

export interface InspectionPlanFilters {
  material_id?: number;
  material_type_id?: number;
  active?: boolean;
}

export interface CreateInspectionPlanPayload {
  name: string;
  description?: string;
  material_id?: number;
  material_type_id?: number;
  criteria: InspectionCriterion[];
  is_active?: boolean;
}

export const listInspectionPlans = (
  opts: InspectionPlanFilters = {},
): Promise<InspectionPlan[]> =>
  api
    .get<ApiEnvelope<InspectionPlan[]>>('/api/v1/inspection-plans', { params: opts })
    .then((r) => r.data.data);

export const getInspectionPlan = (id: number): Promise<InspectionPlan> =>
  api
    .get<ApiEnvelope<InspectionPlan>>(`/api/v1/inspection-plans/${id}`)
    .then((r) => r.data.data);

export const createInspectionPlan = (
  payload: CreateInspectionPlanPayload,
): Promise<InspectionPlan> =>
  api
    .post<ApiEnvelope<InspectionPlan>>('/api/v1/inspection-plans', payload)
    .then((r) => r.data.data);

export const updateInspectionPlan = (
  id: number,
  payload: Partial<CreateInspectionPlanPayload>,
): Promise<InspectionPlan> =>
  api
    .patch<ApiEnvelope<InspectionPlan>>(`/api/v1/inspection-plans/${id}`, payload)
    .then((r) => r.data.data);

export const deleteInspectionPlan = (id: number): Promise<void> =>
  api.delete(`/api/v1/inspection-plans/${id}`).then(() => undefined);

// ── Inspections ─────────────────────────────────────────────────────────────

export interface InspectionFilters {
  material_id?: number;
  lot_number?: string;
  status?: InspectionStatus | string;
  date_from?: string;
  date_to?: string;
  limit?: number;
}

export interface StartInspectionPayload {
  material_id: number;
  lot_number: string;
  quantity_received?: number;
  supplier_lot_ref?: string;
  inspection_plan_id?: number;
}

export interface RecordResultPayload {
  value_numeric?: number;
  value_boolean?: boolean;
  value_text?: string;
  notes?: string;
}

export interface CompleteInspectionPayload {
  notes?: string;
}

export type DispositionAction =
  | 'accept'
  | 'accept_with_deviation'
  | 'rework'
  | 'quarantine'
  | 'scrap'
  | 'return_to_supplier'
  | 'reject';

export interface DispositionPayload {
  disposition: DispositionAction;
  notes?: string;
}

export const listInspections = (
  opts: InspectionFilters = {},
): Promise<Inspection[]> =>
  api
    .get<ApiEnvelope<Inspection[]>>('/api/v1/inspections', { params: opts })
    .then((r) => r.data.data);

export const getInspection = (id: number): Promise<Inspection> =>
  api
    .get<ApiEnvelope<Inspection>>(`/api/v1/inspections/${id}`)
    .then((r) => r.data.data);

export const startInspection = (
  payload: StartInspectionPayload,
): Promise<Inspection> =>
  api
    .post<ApiEnvelope<Inspection>>('/api/v1/inspections', payload)
    .then((r) => r.data.data);

export const recordInspectionResult = (
  inspectionId: number,
  resultId: number,
  payload: RecordResultPayload,
): Promise<InspectionResult> =>
  api
    .patch<ApiEnvelope<InspectionResult>>(
      `/api/v1/inspections/${inspectionId}/results/${resultId}`,
      payload,
    )
    .then((r) => r.data.data);

export const completeInspection = (
  id: number,
  payload: CompleteInspectionPayload = {},
): Promise<Inspection> =>
  api
    .patch<ApiEnvelope<Inspection>>(
      `/api/v1/inspections/${id}/complete`,
      payload,
    )
    .then((r) => r.data.data);

export const getInspectionStats = (
  opts: { days?: number; material_id?: number } = {},
): Promise<InspectionStats> =>
  api
    .get<ApiEnvelope<InspectionStats>>('/api/v1/inspections/stats', { params: opts })
    .then((r) => r.data.data);

export const applyInspectionDisposition = (
  id: number,
  payload: DispositionPayload,
): Promise<Inspection> =>
  api
    .post<ApiEnvelope<Inspection>>(
      `/api/v1/inspections/${id}/disposition`,
      payload,
    )
    .then((r) => r.data.data);
