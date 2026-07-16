import { api } from './client';

export type QualityTaskStatus = 'due' | 'in_progress' | 'done' | 'skipped';

/** One template parameter the operator records (drives the perform form). */
export interface QcParameter {
  name: string;
  type: string; // 'measurement' | 'pass_fail' | …
  unit?: string | null;
}

export interface QualityTask {
  id: number;
  status: QualityTaskStatus;
  due_reason?: string | null;
  fired_at?: string | null;
  is_blocking: boolean;
  quality_control_trigger_id: number;
  trigger_name?: string | null;
  trigger_type?: string | null;
  work_order_id?: number | null;
  work_order_no?: string | null;
  batch_id?: number | null;
  batch_number?: string | null;
  line_id?: number | null;
  line_name?: string | null;
  workstation_name?: string | null;
  parameters: QcParameter[];
  samples_per_check: number;
}

export interface RoamingTrigger {
  id: number;
  name: string;
}

export interface ActiveBatch {
  id: number;
  label: string;
}

export interface QcPallet {
  id: number;
  pallet_no: string | null;
  work_order_id: number | null;
}

export interface QualityTasksData {
  tasks: QualityTask[];
  roamingTriggers: RoamingTrigger[];
  activeBatches: ActiveBatch[];
  pallets: QcPallet[];
}

interface QualityTasksEnvelope {
  data: QualityTask[];
  meta: {
    roaming_triggers: RoamingTrigger[];
    active_batches: ActiveBatch[];
    pallets: QcPallet[];
  };
}

/** GET /api/v1/quality-control-tasks — outstanding controls + perform/roaming metadata. */
export const listQualityTasks = (): Promise<QualityTasksData> =>
  api.get<QualityTasksEnvelope>('/api/v1/quality-control-tasks').then((r) => ({
    tasks: r.data.data,
    roamingTriggers: r.data.meta.roaming_triggers,
    activeBatches: r.data.meta.active_batches,
    pallets: r.data.meta.pallets,
  }));

/** POST /api/v1/quality-control-tasks/{id}/skip — skip an outstanding control. */
export const skipQualityTask = (id: number): Promise<unknown> =>
  api.post(`/api/v1/quality-control-tasks/${id}/skip`).then((r) => r.data);

export interface QcSample {
  sample_number: number;
  parameter_name: string;
  parameter_type: 'measurement' | 'pass_fail';
  value_numeric?: number | null;
  is_passed?: boolean;
}

export interface PerformQualityTaskPayload {
  samples: QcSample[];
  notes?: string | null;
  pallet_id?: number | null;
}

/** POST /api/v1/quality-control-tasks/{id}/perform — record the control result and complete the task. */
export const performQualityTask = (
  id: number,
  payload: PerformQualityTaskPayload,
): Promise<unknown> =>
  api.post(`/api/v1/quality-control-tasks/${id}/perform`, payload).then((r) => r.data);

export interface RaiseRoamingPayload {
  quality_control_trigger_id: number;
  batch_id: number;
}

/** POST /api/v1/quality-control-tasks — raise an ad-hoc roaming control. */
export const raiseRoamingTask = (payload: RaiseRoamingPayload): Promise<unknown> =>
  api.post('/api/v1/quality-control-tasks', payload).then((r) => r.data);
