import { api } from './client';
import type { ApiEnvelope, ApiPaginated, WorkOrder, WorkOrderStatus } from '@/types/api';

export interface WorkOrderFilters {
  status?: WorkOrderStatus | WorkOrderStatus[];
  line_id?: number;
  week_number?: number;
  per_page?: number;
  page?: number;
}

export const listWorkOrders = (filters: WorkOrderFilters = {}): Promise<WorkOrder[]> =>
  api
    .get<ApiPaginated<WorkOrder> | ApiEnvelope<WorkOrder[]>>('/api/v1/work-orders', {
      params: filters,
    })
    .then((r) => r.data.data);

export const getWorkOrder = (id: number): Promise<WorkOrder> =>
  api.get<ApiEnvelope<WorkOrder>>(`/api/v1/work-orders/${id}`).then((r) => r.data.data);

export type WorkOrderTransition =
  | 'accept'
  | 'reject'
  | 'cancel'
  | 'pause'
  | 'resume'
  | 'reopen'
  | 'complete';

export const transitionWorkOrder = (
  id: number,
  transition: WorkOrderTransition,
): Promise<WorkOrder> =>
  api
    .post<ApiEnvelope<WorkOrder>>(`/api/v1/work-orders/${id}/${transition}`)
    .then((r) => r.data.data);

export interface CreateWorkOrderPayload {
  order_no: string;
  line_id?: number;
  product_type_id?: number;
  planned_qty: number;
  priority?: number;
  due_date?: string;
  description?: string;
}

export const createWorkOrder = (payload: CreateWorkOrderPayload): Promise<WorkOrder> =>
  api
    .post<ApiEnvelope<WorkOrder>>('/api/v1/work-orders', payload)
    .then((r) => r.data.data);

export const updateWorkOrder = (
  id: number,
  payload: Partial<CreateWorkOrderPayload>,
): Promise<WorkOrder> =>
  api
    .patch<ApiEnvelope<WorkOrder>>(`/api/v1/work-orders/${id}`, payload)
    .then((r) => r.data.data);
