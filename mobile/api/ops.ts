import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

// ── Types ────────────────────────────────────────────────────────────────────

export type CompanyType = 'supplier' | 'customer' | 'both';

export interface Company {
  id: number;
  code: string;
  name: string;
  tax_id?: string | null;
  type: CompanyType;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  is_active: boolean;
}

export interface CostSource {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  unit_cost?: string | number | null;
  unit?: string | null;
  currency?: string | null;
  is_active: boolean;
}

export interface AnomalyReason {
  id: number;
  code: string;
  name: string;
  category?: string | null;
  description?: string | null;
  is_active: boolean;
}

export interface Subassembly {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  product_type_id?: number | null;
  product_type?: { id: number; name: string };
  is_active: boolean;
}

export interface Shift {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  days_of_week?: number[] | null;
  line_id?: number | null;
  line?: { id: number; name: string } | null;
  is_active: boolean;
}

// ── Companies ───────────────────────────────────────────────────────────────

export const listCompanies = (
  opts: { include_inactive?: boolean; type?: CompanyType; q?: string } = {},
): Promise<Company[]> =>
  api.get<ApiEnvelope<Company[]>>('/api/v1/companies', { params: opts }).then((r) => r.data.data);

export const getCompany = (id: number): Promise<Company> =>
  api.get<ApiEnvelope<Company>>(`/api/v1/companies/${id}`).then((r) => r.data.data);

export interface CreateCompanyPayload {
  code: string;
  name: string;
  type: CompanyType;
  tax_id?: string;
  email?: string;
  phone?: string;
  address?: string;
  is_active?: boolean;
}

export const createCompany = (payload: CreateCompanyPayload): Promise<Company> =>
  api.post<ApiEnvelope<Company>>('/api/v1/companies', payload).then((r) => r.data.data);

export const updateCompany = (
  id: number,
  payload: Partial<CreateCompanyPayload>,
): Promise<Company> =>
  api.patch<ApiEnvelope<Company>>(`/api/v1/companies/${id}`, payload).then((r) => r.data.data);

export const deleteCompany = (id: number): Promise<void> =>
  api.delete(`/api/v1/companies/${id}`).then(() => undefined);

export const toggleCompanyActive = (id: number): Promise<Company> =>
  api
    .post<ApiEnvelope<Company>>(`/api/v1/companies/${id}/toggle-active`)
    .then((r) => r.data.data);

// ── Cost sources ────────────────────────────────────────────────────────────

export const listCostSources = (includeInactive = false): Promise<CostSource[]> =>
  api
    .get<ApiEnvelope<CostSource[]>>('/api/v1/cost-sources', {
      params: { include_inactive: includeInactive },
    })
    .then((r) => r.data.data);

export const getCostSource = (id: number): Promise<CostSource> =>
  api.get<ApiEnvelope<CostSource>>(`/api/v1/cost-sources/${id}`).then((r) => r.data.data);

export interface CreateCostSourcePayload {
  code: string;
  name: string;
  description?: string;
  unit_cost?: number;
  unit?: string;
  currency?: string;
  is_active?: boolean;
}

export const createCostSource = (payload: CreateCostSourcePayload): Promise<CostSource> =>
  api.post<ApiEnvelope<CostSource>>('/api/v1/cost-sources', payload).then((r) => r.data.data);

export const updateCostSource = (
  id: number,
  payload: Partial<CreateCostSourcePayload>,
): Promise<CostSource> =>
  api
    .patch<ApiEnvelope<CostSource>>(`/api/v1/cost-sources/${id}`, payload)
    .then((r) => r.data.data);

export const deleteCostSource = (id: number): Promise<void> =>
  api.delete(`/api/v1/cost-sources/${id}`).then(() => undefined);

export const toggleCostSourceActive = (id: number): Promise<CostSource> =>
  api
    .post<ApiEnvelope<CostSource>>(`/api/v1/cost-sources/${id}/toggle-active`)
    .then((r) => r.data.data);

// ── Anomaly reasons ─────────────────────────────────────────────────────────

export const listAnomalyReasons = (
  opts: { include_inactive?: boolean; category?: string } = {},
): Promise<AnomalyReason[]> =>
  api
    .get<ApiEnvelope<AnomalyReason[]>>('/api/v1/anomaly-reasons', { params: opts })
    .then((r) => r.data.data);

export const getAnomalyReason = (id: number): Promise<AnomalyReason> =>
  api
    .get<ApiEnvelope<AnomalyReason>>(`/api/v1/anomaly-reasons/${id}`)
    .then((r) => r.data.data);

export interface CreateAnomalyReasonPayload {
  code: string;
  name: string;
  category?: string;
  description?: string;
  is_active?: boolean;
}

export const createAnomalyReason = (
  payload: CreateAnomalyReasonPayload,
): Promise<AnomalyReason> =>
  api
    .post<ApiEnvelope<AnomalyReason>>('/api/v1/anomaly-reasons', payload)
    .then((r) => r.data.data);

export const updateAnomalyReason = (
  id: number,
  payload: Partial<CreateAnomalyReasonPayload>,
): Promise<AnomalyReason> =>
  api
    .patch<ApiEnvelope<AnomalyReason>>(`/api/v1/anomaly-reasons/${id}`, payload)
    .then((r) => r.data.data);

export const deleteAnomalyReason = (id: number): Promise<void> =>
  api.delete(`/api/v1/anomaly-reasons/${id}`).then(() => undefined);

// ── Subassemblies ───────────────────────────────────────────────────────────

export const listSubassemblies = (
  opts: { include_inactive?: boolean; product_type_id?: number } = {},
): Promise<Subassembly[]> =>
  api
    .get<ApiEnvelope<Subassembly[]>>('/api/v1/subassemblies', { params: opts })
    .then((r) => r.data.data);

export const getSubassembly = (id: number): Promise<Subassembly> =>
  api.get<ApiEnvelope<Subassembly>>(`/api/v1/subassemblies/${id}`).then((r) => r.data.data);

export interface CreateSubassemblyPayload {
  code: string;
  name: string;
  description?: string;
  product_type_id?: number;
  is_active?: boolean;
}

export const createSubassembly = (payload: CreateSubassemblyPayload): Promise<Subassembly> =>
  api.post<ApiEnvelope<Subassembly>>('/api/v1/subassemblies', payload).then((r) => r.data.data);

export const updateSubassembly = (
  id: number,
  payload: Partial<CreateSubassemblyPayload>,
): Promise<Subassembly> =>
  api
    .patch<ApiEnvelope<Subassembly>>(`/api/v1/subassemblies/${id}`, payload)
    .then((r) => r.data.data);

export const deleteSubassembly = (id: number): Promise<void> =>
  api.delete(`/api/v1/subassemblies/${id}`).then(() => undefined);

// ── Shifts ──────────────────────────────────────────────────────────────────

export const listShifts = (
  opts: { include_inactive?: boolean; line_id?: number } = {},
): Promise<Shift[]> =>
  api.get<ApiEnvelope<Shift[]>>('/api/v1/shifts', { params: opts }).then((r) => r.data.data);

export const getShift = (id: number): Promise<Shift> =>
  api.get<ApiEnvelope<Shift>>(`/api/v1/shifts/${id}`).then((r) => r.data.data);

export interface CreateShiftPayload {
  name: string;
  start_time: string;
  end_time: string;
  days_of_week?: number[];
  line_id?: number;
  is_active?: boolean;
}

export const createShift = (payload: CreateShiftPayload): Promise<Shift> =>
  api.post<ApiEnvelope<Shift>>('/api/v1/shifts', payload).then((r) => r.data.data);

export const updateShift = (
  id: number,
  payload: Partial<CreateShiftPayload>,
): Promise<Shift> =>
  api.patch<ApiEnvelope<Shift>>(`/api/v1/shifts/${id}`, payload).then((r) => r.data.data);

export const deleteShift = (id: number): Promise<void> =>
  api.delete(`/api/v1/shifts/${id}`).then(() => undefined);
