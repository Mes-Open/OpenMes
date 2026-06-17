import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

// ── Process Confirmations ─────────────────────────────────────────────────

export type ConfirmationType = 'parameters' | 'drying' | 'custom';

export interface ProcessConfirmation {
  id: number;
  batch_id: number;
  batch_step_id?: number | null;
  confirmation_type: ConfirmationType;
  notes?: string | null;
  value?: string | null;
  confirmed_by_id?: number | null;
  confirmed_by?: { id: number; username: string } | null;
  confirmed_at?: string | null;
  created_at?: string;
}

export interface ConfirmationStatus {
  confirmed_today: boolean;
  total_confirmations: number;
}

export const listProcessConfirmations = (batchId: number): Promise<ProcessConfirmation[]> =>
  api
    .get<ApiEnvelope<ProcessConfirmation[]>>(`/api/v1/batches/${batchId}/confirmations`)
    .then((r) => r.data.data);

export interface ConfirmInput {
  confirmation_type: ConfirmationType;
  batch_step_id?: number | null;
  notes?: string | null;
  value?: string | null;
}

export const createProcessConfirmation = (
  batchId: number,
  input: ConfirmInput,
): Promise<ProcessConfirmation> =>
  api
    .post<ApiEnvelope<ProcessConfirmation>>(`/api/v1/batches/${batchId}/confirmations`, input)
    .then((r) => r.data.data);

export const getConfirmationStatus = (batchId: number): Promise<ConfirmationStatus> =>
  api
    .get<ApiEnvelope<ConfirmationStatus>>(`/api/v1/batches/${batchId}/confirmations/status`)
    .then((r) => r.data.data);

// ── Quality Checks ────────────────────────────────────────────────────────

export type QcParameterType = 'measurement' | 'pass_fail';

export interface QcSampleInput {
  sample_number: number;
  parameter_name: string;
  parameter_type: QcParameterType;
  value_numeric?: number | null;
  value_boolean?: boolean | null;
  is_passed?: boolean | null;
}

export interface QcSample extends QcSampleInput {
  id: number;
  quality_check_id: number;
}

export interface QualityCheck {
  id: number;
  batch_id: number;
  quality_check_template_id?: number | null;
  production_quantity?: number | null;
  notes?: string | null;
  checked_by_id?: number | null;
  checked_by?: { id: number; username: string } | null;
  checked_at?: string | null;
  samples?: QcSample[];
  created_at?: string;
}

export interface QcParameterDef {
  name: string;
  type: QcParameterType;
  unit?: string | null;
  min?: number | null;
  max?: number | null;
}

export interface QualityCheckTemplate {
  id: number;
  process_template_id: number;
  name: string;
  min_checks_per_batch?: number | null;
  min_checks_per_day?: number | null;
  samples_per_check?: number | null;
  parameters: QcParameterDef[];
}

export interface QcStatus {
  required: boolean;
  template?: QualityCheckTemplate | null;
  checks_done: number;
  checks_required?: number | null;
  is_satisfied: boolean;
  [k: string]: unknown;
}

export const listQualityChecks = (batchId: number): Promise<QualityCheck[]> =>
  api
    .get<ApiEnvelope<QualityCheck[]>>(`/api/v1/batches/${batchId}/quality-checks`)
    .then((r) => r.data.data);

export interface QcCheckInput {
  samples: QcSampleInput[];
  production_quantity?: number | null;
  quality_check_template_id?: number | null;
  notes?: string | null;
}

export const createQualityCheck = (
  batchId: number,
  input: QcCheckInput,
): Promise<QualityCheck> =>
  api
    .post<ApiEnvelope<QualityCheck>>(`/api/v1/batches/${batchId}/quality-checks`, input)
    .then((r) => r.data.data);

export const getQcStatus = (batchId: number): Promise<QcStatus> =>
  api
    .get<ApiEnvelope<QcStatus>>(`/api/v1/batches/${batchId}/quality-checks/status`)
    .then((r) => r.data.data);

export const listQcTemplatesForProcessTemplate = (
  processTemplateId: number,
): Promise<QualityCheckTemplate[]> =>
  api
    .get<ApiEnvelope<QualityCheckTemplate[]>>(
      `/api/v1/process-templates/${processTemplateId}/qc-templates`,
    )
    .then((r) => r.data.data);

export interface QcTemplateInput {
  name: string;
  parameters: QcParameterDef[];
  min_checks_per_batch?: number | null;
  min_checks_per_day?: number | null;
  samples_per_check?: number | null;
}

export const createQcTemplate = (
  processTemplateId: number,
  input: QcTemplateInput,
): Promise<QualityCheckTemplate> =>
  api
    .post<ApiEnvelope<QualityCheckTemplate>>(
      `/api/v1/process-templates/${processTemplateId}/qc-templates`,
      input,
    )
    .then((r) => r.data.data);

export const updateQcTemplate = (
  id: number,
  input: Partial<QcTemplateInput>,
): Promise<QualityCheckTemplate> =>
  api
    .patch<ApiEnvelope<QualityCheckTemplate>>(`/api/v1/qc-templates/${id}`, input)
    .then((r) => r.data.data);

export const deleteQcTemplate = (id: number): Promise<void> =>
  api.delete(`/api/v1/qc-templates/${id}`).then(() => undefined);

// ── Packaging Checklist ───────────────────────────────────────────────────

export interface PackagingChecklist {
  id: number;
  batch_id: number;
  udi_readable: boolean;
  packaging_condition: boolean;
  labels_readable: boolean;
  label_matches_product: boolean;
  notes?: string | null;
  submitted_by_id?: number | null;
  submitted_at?: string | null;
}

export interface PackagingChecklistResponse {
  data: PackagingChecklist | null;
  is_complete: boolean;
}

export const getPackagingChecklist = (
  batchId: number,
): Promise<PackagingChecklistResponse> =>
  api
    .get<PackagingChecklistResponse>(`/api/v1/batches/${batchId}/packaging-checklist`)
    .then((r) => r.data);

export interface PackagingChecklistInput {
  udi_readable: boolean;
  packaging_condition: boolean;
  labels_readable: boolean;
  label_matches_product: boolean;
  notes?: string | null;
}

export const submitPackagingChecklist = (
  batchId: number,
  input: PackagingChecklistInput,
): Promise<PackagingChecklist> =>
  api
    .post<ApiEnvelope<PackagingChecklist>>(`/api/v1/batches/${batchId}/packaging-checklist`, input)
    .then((r) => r.data.data);
