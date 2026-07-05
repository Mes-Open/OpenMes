import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface CrewBreakWindow {
  id: number;
  crew_id: number;
  crew_name?: string | null;
  name: string;
  start_time: string;
  end_time: string;
  days_of_week: number[];
  is_active: boolean;
}

export interface CreateBreakWindowPayload {
  crew_id: number;
  name: string;
  start_time: string;
  end_time: string;
  days_of_week: number[];
  is_active?: boolean;
}

export const listCrewBreakWindows = (): Promise<CrewBreakWindow[]> =>
  api.get<ApiEnvelope<CrewBreakWindow[]>>('/api/v1/crew-break-windows').then((r) => r.data.data);

export const createCrewBreakWindow = (
  payload: CreateBreakWindowPayload,
): Promise<CrewBreakWindow> =>
  api.post<ApiEnvelope<CrewBreakWindow>>('/api/v1/crew-break-windows', payload).then((r) => r.data.data);

export const deleteCrewBreakWindow = (id: number): Promise<void> =>
  api.delete(`/api/v1/crew-break-windows/${id}`).then(() => undefined);
