import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface TrashItem {
  type: string;
  id: number;
  label: string;
  deleted_at: string | null;
  deleted_by: string | null;
}

export interface TrashData {
  items: TrashItem[];
  counts: Record<string, number>;
  selected_type: string | null;
}

export const getTrash = (type?: string): Promise<TrashData> =>
  api
    .get<ApiEnvelope<TrashData>>('/api/v1/trash', { params: type ? { type } : {} })
    .then((r) => r.data.data);

export const restoreTrash = (type: string, id: number): Promise<void> =>
  api.post(`/api/v1/trash/${type}/${id}/restore`).then(() => undefined);
