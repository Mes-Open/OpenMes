// Schedule planner write — minute-level move / resize of work orders.
// Backend at PUT /api/v1/schedule/{workOrder} and /resize.

import type { ApiEnvelope, WorkOrder } from '@/types/api';
import { api } from './client';

/** Update one or more planner fields on a work order. */
export interface ScheduleUpdateInput {
  line_id?: number | null;
  due_date?: string | null;
  end_date?: string | null;
  week_number?: number | null;
  shift_number?: number | null;
  end_shift_number?: number | null;
  /** ISO 8601 (minute precision OK). */
  planned_start_at?: string | null;
  /** ISO 8601. Must be strictly after planned_start_at. */
  planned_end_at?: string | null;
  /** Skip the same-line overlap check. Use only when the caller already
   * confirmed the conflict with the user. */
  force_conflict?: boolean;
}

/** Specifically for the resize action — both timestamps required. */
export interface ScheduleResizeInput {
  planned_start_at: string;
  planned_end_at: string;
  force_conflict?: boolean;
}

/**
 * Error thrown on a 409 conflict response so call sites can prompt the user
 * and retry with `force_conflict: true`. Other errors fall through unchanged.
 */
export class ScheduleConflictError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ScheduleConflictError';
  }
}

async function unwrapConflict<T>(p: Promise<T>): Promise<T> {
  try {
    return await p;
  } catch (e) {
    const err = e as { response?: { status?: number; data?: { conflict?: boolean; message?: string } } };
    if (err?.response?.status === 409 && err.response.data?.conflict) {
      throw new ScheduleConflictError(err.response.data.message ?? 'Time slot conflict');
    }
    throw e;
  }
}

export const updateScheduleOrder = (
  workOrderId: number,
  input: ScheduleUpdateInput,
): Promise<WorkOrder> =>
  unwrapConflict(
    api
      .put<ApiEnvelope<WorkOrder>>(`/api/v1/schedule/${workOrderId}`, input)
      .then((r) => r.data.data),
  );

export const resizeScheduleOrder = (
  workOrderId: number,
  input: ScheduleResizeInput,
): Promise<WorkOrder> =>
  unwrapConflict(
    api
      .put<ApiEnvelope<WorkOrder>>(`/api/v1/schedule/${workOrderId}/resize`, input)
      .then((r) => r.data.data),
  );
