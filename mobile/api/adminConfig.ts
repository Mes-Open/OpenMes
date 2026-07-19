// Admin-config endpoints: external-system integrations, custom-field
// definitions and QC triggers.

import type { ApiEnvelope } from '@/types/api';
import { api } from './client';

export interface Integration {
  id: number;
  system_type: string;
  system_name: string;
  is_active: boolean;
  material_sources_count: number;
}

export interface IntegrationInput {
  system_type: string;
  system_name: string;
  is_active?: boolean;
}

export const listIntegrations = (): Promise<Integration[]> =>
  api.get<ApiEnvelope<Integration[]>>('/api/v1/integrations').then((r) => r.data.data);

export const createIntegration = (input: IntegrationInput): Promise<Integration> =>
  api.post<ApiEnvelope<Integration>>('/api/v1/integrations', input).then((r) => r.data.data);

export const updateIntegration = (id: number, input: IntegrationInput): Promise<Integration> =>
  api.patch<ApiEnvelope<Integration>>(`/api/v1/integrations/${id}`, input).then((r) => r.data.data);

export const toggleIntegrationActive = (id: number): Promise<{ id: number; is_active: boolean }> =>
  api
    .post<ApiEnvelope<{ id: number; is_active: boolean }>>(`/api/v1/integrations/${id}/toggle-active`)
    .then((r) => r.data.data);

export const deleteIntegration = (id: number): Promise<void> =>
  api.delete(`/api/v1/integrations/${id}`).then(() => undefined);

export interface CustomFieldOption {
  value: string;
  label: string;
}

export interface CustomField {
  id: number;
  entity_type: string;
  entity_label: string;
  key: string;
  label: string;
  type: string;
  type_label: string;
  config?: { options?: CustomFieldOption[] };
  options_count: number;
  required: boolean;
  position: number;
  is_active: boolean;
}

export interface CustomFieldInput {
  entity_type: string;
  key: string;
  label: string;
  type: string;
  required?: boolean;
  position?: number;
  is_active?: boolean;
  config?: { options?: CustomFieldOption[] };
}

export interface CustomFieldMeta {
  entities: { value: string; label: string }[];
  types: { value: string; label: string; has_options: boolean }[];
}

export const listCustomFields = (): Promise<CustomField[]> =>
  api.get<ApiEnvelope<CustomField[]>>('/api/v1/custom-fields').then((r) => r.data.data);

export const getCustomFieldMeta = (): Promise<CustomFieldMeta> =>
  api.get<ApiEnvelope<CustomFieldMeta>>('/api/v1/custom-fields/meta').then((r) => r.data.data);

export const createCustomField = (input: CustomFieldInput): Promise<CustomField> =>
  api.post<ApiEnvelope<CustomField>>('/api/v1/custom-fields', input).then((r) => r.data.data);

export const updateCustomField = (id: number, input: CustomFieldInput): Promise<CustomField> =>
  api.patch<ApiEnvelope<CustomField>>(`/api/v1/custom-fields/${id}`, input).then((r) => r.data.data);

export const toggleCustomFieldActive = (id: number): Promise<{ id: number; is_active: boolean }> =>
  api
    .post<ApiEnvelope<{ id: number; is_active: boolean }>>(`/api/v1/custom-fields/${id}/toggle-active`)
    .then((r) => r.data.data);

export const deleteCustomField = (id: number): Promise<void> =>
  api.delete(`/api/v1/custom-fields/${id}`).then(() => undefined);

export interface QcTrigger {
  id: number;
  name: string;
  trigger_type: string;
  quality_check_template_id?: number | null;
  template_name: string | null;
  line_id?: number | null;
  workstation_id?: number | null;
  product_type_id?: number | null;
  scope: string | null;
  threshold_n: number | null;
  downtime_min_minutes: number | null;
  is_blocking: boolean;
  is_active: boolean;
}

export interface QcTriggerInput {
  name: string;
  trigger_type: string;
  quality_check_template_id?: number | null;
  line_id?: number | null;
  workstation_id?: number | null;
  product_type_id?: number | null;
  threshold_n?: number | null;
  downtime_min_minutes?: number | null;
  is_blocking?: boolean;
  is_active?: boolean;
}

export interface NamedRef {
  id: number;
  name: string;
}

export interface QcTriggerMeta {
  types: { value: string; label: string; needs_threshold: boolean }[];
  templates: NamedRef[];
  lines: NamedRef[];
  workstations: NamedRef[];
  product_types: NamedRef[];
}

export const listQcTriggers = (): Promise<QcTrigger[]> =>
  api.get<ApiEnvelope<QcTrigger[]>>('/api/v1/quality-control-triggers').then((r) => r.data.data);

export const getQcTriggerMeta = (): Promise<QcTriggerMeta> =>
  api.get<ApiEnvelope<QcTriggerMeta>>('/api/v1/quality-control-triggers/meta').then((r) => r.data.data);

export const createQcTrigger = (input: QcTriggerInput): Promise<QcTrigger> =>
  api.post<ApiEnvelope<QcTrigger>>('/api/v1/quality-control-triggers', input).then((r) => r.data.data);

export const updateQcTrigger = (id: number, input: QcTriggerInput): Promise<QcTrigger> =>
  api.patch<ApiEnvelope<QcTrigger>>(`/api/v1/quality-control-triggers/${id}`, input).then((r) => r.data.data);

export const toggleQcTriggerActive = (id: number): Promise<{ id: number; is_active: boolean }> =>
  api
    .post<ApiEnvelope<{ id: number; is_active: boolean }>>(`/api/v1/quality-control-triggers/${id}/toggle-active`)
    .then((r) => r.data.data);

export const deleteQcTrigger = (id: number): Promise<void> =>
  api.delete(`/api/v1/quality-control-triggers/${id}`).then(() => undefined);
