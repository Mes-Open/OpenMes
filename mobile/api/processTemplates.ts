import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface TemplateStep {
  id: number;
  process_template_id: number;
  step_number: number;
  name: string;
  instruction?: string | null;
  estimated_duration_minutes?: number | null;
  workstation_id?: number | null;
  workstation?: { id: number; name: string; code?: string } | null;
}

export interface ProcessTemplate {
  id: number;
  product_type_id: number;
  name: string;
  version: number;
  is_active: boolean;
  steps?: TemplateStep[];
  product_type?: { id: number; name: string; code?: string };
  created_at?: string;
  updated_at?: string;
}

export const listTemplatesForProductType = (
  productTypeId: number,
  includeInactive = false,
): Promise<ProcessTemplate[]> =>
  api
    .get<ApiEnvelope<ProcessTemplate[]>>(`/api/v1/product-types/${productTypeId}/process-templates`, {
      params: { include_inactive: includeInactive },
    })
    .then((r) => r.data.data);

export const getTemplate = (id: number): Promise<ProcessTemplate> =>
  api
    .get<ApiEnvelope<ProcessTemplate>>(`/api/v1/process-templates/${id}`)
    .then((r) => r.data.data);

export const deleteTemplate = (id: number): Promise<void> =>
  api.delete(`/api/v1/process-templates/${id}`).then(() => undefined);

export const toggleTemplateActive = (id: number): Promise<ProcessTemplate> =>
  api
    .post<ApiEnvelope<ProcessTemplate>>(`/api/v1/process-templates/${id}/toggle-active`)
    .then((r) => r.data.data);

export interface ProcessTemplateInput {
  name: string;
  version?: number | null;
  is_active?: boolean | null;
}

export const createTemplate = (
  productTypeId: number,
  input: ProcessTemplateInput,
): Promise<ProcessTemplate> =>
  api
    .post<ApiEnvelope<ProcessTemplate>>(
      `/api/v1/product-types/${productTypeId}/process-templates`,
      input,
    )
    .then((r) => r.data.data);

export const updateTemplate = (
  id: number,
  input: Partial<ProcessTemplateInput>,
): Promise<ProcessTemplate> =>
  api
    .patch<ApiEnvelope<ProcessTemplate>>(`/api/v1/process-templates/${id}`, input)
    .then((r) => r.data.data);

// ── Template steps ────────────────────────────────────────────────────────

export interface TemplateStepInput {
  name: string;
  step_number?: number | null;
  instruction?: string | null;
  estimated_duration_minutes?: number | null;
  workstation_id?: number | null;
}

export const addTemplateStep = (
  processTemplateId: number,
  input: TemplateStepInput,
): Promise<TemplateStep> =>
  api
    .post<ApiEnvelope<TemplateStep>>(
      `/api/v1/process-templates/${processTemplateId}/steps`,
      input,
    )
    .then((r) => r.data.data);

export const updateTemplateStep = (
  stepId: number,
  input: Partial<TemplateStepInput>,
): Promise<TemplateStep> =>
  api
    .patch<ApiEnvelope<TemplateStep>>(`/api/v1/template-steps/${stepId}`, input)
    .then((r) => r.data.data);

export const deleteTemplateStep = (stepId: number): Promise<void> =>
  api.delete(`/api/v1/template-steps/${stepId}`).then(() => undefined);

export const reorderTemplateSteps = (
  processTemplateId: number,
  step_ids: number[],
): Promise<TemplateStep[]> =>
  api
    .post<ApiEnvelope<TemplateStep[]>>(
      `/api/v1/process-templates/${processTemplateId}/steps/reorder`,
      { step_ids },
    )
    .then((r) => r.data.data);
