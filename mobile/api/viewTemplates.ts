import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export type ViewColumnSource = 'field' | 'extra_data';

export interface ViewColumn {
  label: string;
  key: string;
  source: ViewColumnSource;
}

export interface ViewTemplate {
  id: number;
  name: string;
  description: string | null;
  lines_count: number;
  columns_count: number;
  columns?: ViewColumn[];
}

export interface ViewTemplateInput {
  name: string;
  description?: string | null;
  columns: ViewColumn[];
}

export const listViewTemplates = (): Promise<ViewTemplate[]> =>
  api.get<ApiEnvelope<ViewTemplate[]>>('/api/v1/view-templates').then((r) => r.data.data);

export const createViewTemplate = (input: ViewTemplateInput): Promise<ViewTemplate> =>
  api.post<ApiEnvelope<ViewTemplate>>('/api/v1/view-templates', input).then((r) => r.data.data);

export const updateViewTemplate = (id: number, input: ViewTemplateInput): Promise<ViewTemplate> =>
  api.patch<ApiEnvelope<ViewTemplate>>(`/api/v1/view-templates/${id}`, input).then((r) => r.data.data);

export const deleteViewTemplate = (id: number): Promise<void> =>
  api.delete(`/api/v1/view-templates/${id}`).then(() => undefined);
