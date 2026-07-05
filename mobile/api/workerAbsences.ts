import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export const ABSENCE_TYPES = ['vacation', 'sick', 'personal', 'training', 'other'] as const;
export type AbsenceType = (typeof ABSENCE_TYPES)[number];
export type AbsenceStatus = 'approved' | 'pending' | 'rejected';

export interface WorkerAbsence {
  id: number;
  worker_id: number;
  worker_name?: string | null;
  type: AbsenceType;
  status: AbsenceStatus;
  starts_on?: string | null;
  ends_on?: string | null;
  all_day: boolean;
  reason?: string | null;
}

export interface CreateAbsencePayload {
  worker_id: number;
  type: AbsenceType;
  starts_on: string;
  ends_on: string;
  status?: AbsenceStatus;
  reason?: string;
}

export const listWorkerAbsences = (): Promise<WorkerAbsence[]> =>
  api.get<ApiEnvelope<WorkerAbsence[]>>('/api/v1/worker-absences').then((r) => r.data.data);

export const createWorkerAbsence = (payload: CreateAbsencePayload): Promise<WorkerAbsence> =>
  api.post<ApiEnvelope<WorkerAbsence>>('/api/v1/worker-absences', payload).then((r) => r.data.data);

export const deleteWorkerAbsence = (id: number): Promise<void> =>
  api.delete(`/api/v1/worker-absences/${id}`).then(() => undefined);
