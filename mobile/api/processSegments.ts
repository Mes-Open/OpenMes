import { api } from './client';
import type { ApiEnvelope, ApiPaginated } from '@/types/api';

export type ProcessSegmentType =
  | 'production'
  | 'inspection'
  | 'maintenance'
  | 'setup'
  | 'cleaning'
  | 'transport'
  | 'other';

export interface ProcessSegment {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  segment_type: ProcessSegmentType | string;
  workstation_type_id?: number | null;
  estimated_duration_minutes?: number | null;
  required_operators?: number | null;
  standard_instruction?: string | null;
  required_skill_ids?: number[] | null;
  parameters?: Record<string, unknown> | null;
  is_active: boolean;
  created_by_id?: number;
  tenant_id?: number;
  created_at?: string;
  updated_at?: string;
  workstationType?: { id: number; name: string; code?: string } | null;
  createdBy?: { id: number; name?: string; username?: string } | null;
  template_steps_count?: number;
  templateSteps_count?: number;
}

export interface ProcessSegmentFilters {
  segment_type?: ProcessSegmentType | string;
  workstation_type_id?: number;
  is_active?: boolean;
  include_inactive?: boolean;
  search?: string;
  per_page?: number;
  page?: number;
}

export interface CreateProcessSegmentPayload {
  code: string;
  name: string;
  description?: string;
  segment_type: ProcessSegmentType;
  workstation_type_id?: number;
  estimated_duration_minutes?: number;
  required_operators?: number;
  standard_instruction?: string;
  required_skill_ids?: number[];
  parameters?: Record<string, unknown>;
  is_active?: boolean;
}

export const listProcessSegments = (
  opts: ProcessSegmentFilters = {},
): Promise<ApiPaginated<ProcessSegment>> =>
  api
    .get<ApiPaginated<ProcessSegment>>('/api/v1/process-segments', { params: opts })
    .then((r) => r.data);

export const getProcessSegment = (id: number): Promise<ProcessSegment> =>
  api
    .get<ApiEnvelope<ProcessSegment>>(`/api/v1/process-segments/${id}`)
    .then((r) => r.data.data);

export const createProcessSegment = (
  payload: CreateProcessSegmentPayload,
): Promise<ProcessSegment> =>
  api
    .post<ApiEnvelope<ProcessSegment>>('/api/v1/process-segments', payload)
    .then((r) => r.data.data);

export const updateProcessSegment = (
  id: number,
  payload: Partial<CreateProcessSegmentPayload>,
): Promise<ProcessSegment> =>
  api
    .patch<ApiEnvelope<ProcessSegment>>(`/api/v1/process-segments/${id}`, payload)
    .then((r) => r.data.data);

export const deleteProcessSegment = (id: number): Promise<void> =>
  api.delete(`/api/v1/process-segments/${id}`).then(() => undefined);
