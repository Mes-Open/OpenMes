import { api } from './client';
import type { ApiEnvelope } from '@/types/api';

export interface MachineTile {
  id: number;
  name: string;
  line?: string | null;
  state: string;
  color: string;
  since?: string | null;
  availability?: number | null;
  quality?: number | null;
  good?: number | null;
  reject?: number | null;
  metadata?: unknown;
}

export interface MachineMonitor {
  tiles: MachineTile[];
  states: string[];
}

/** GET /api/v1/machine-monitor — live fleet status tiles + valid state set. */
export const getMachineMonitor = (): Promise<MachineMonitor> =>
  api.get<ApiEnvelope<MachineMonitor>>('/api/v1/machine-monitor').then((r) => r.data.data);

/** POST /api/v1/machine-monitor/{id}/state — manual state override. */
export const setWorkstationState = (
  workstationId: number,
  state: string,
  note?: string,
): Promise<unknown> =>
  api
    .post(`/api/v1/machine-monitor/${workstationId}/state`, { state, note })
    .then((r) => r.data);
