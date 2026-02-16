import apiClient from './client';
import type { WorkOrder, ApiResponse, Batch, BatchStep, Line } from '../types';

export const workOrdersApi = {
  getAll: async (filters?: { status?: string; line_id?: number }): Promise<WorkOrder[]> => {
    const params = new URLSearchParams();
    if (filters?.status) params.append('status', filters.status);
    if (filters?.line_id) params.append('line_id', filters.line_id.toString());

    const response = await apiClient.get<ApiResponse<WorkOrder[]>>(`/v1/work-orders?${params}`);
    return response.data.data;
  },

  getById: async (id: number): Promise<WorkOrder> => {
    const response = await apiClient.get<ApiResponse<WorkOrder>>(`/v1/work-orders/${id}`);
    return response.data.data;
  },

  getBatches: async (workOrderId: number): Promise<Batch[]> => {
    const response = await apiClient.get<ApiResponse<Batch[]>>(`/v1/work-orders/${workOrderId}/batches`);
    return response.data.data;
  },

  createBatch: async (workOrderId: number, targetQty: number): Promise<Batch> => {
    const response = await apiClient.post<ApiResponse<Batch>>(
      `/v1/work-orders/${workOrderId}/batches`,
      { target_qty: targetQty }
    );
    return response.data.data;
  },
};

export const batchesApi = {
  getSteps: async (batchId: number): Promise<BatchStep[]> => {
    const response = await apiClient.get<ApiResponse<BatchStep[]>>(`/v1/batches/${batchId}/steps`);
    return response.data.data;
  },
};

export const stepsApi = {
  start: async (stepId: number): Promise<BatchStep> => {
    const response = await apiClient.post<ApiResponse<BatchStep>>(`/v1/batch-steps/${stepId}/start`);
    return response.data.data;
  },

  complete: async (stepId: number, producedQty?: number): Promise<BatchStep> => {
    const response = await apiClient.post<ApiResponse<BatchStep>>(
      `/v1/batch-steps/${stepId}/complete`,
      producedQty ? { produced_qty: producedQty } : {}
    );
    return response.data.data;
  },

  reportProblem: async (stepId: number, issueTypeId: number, title: string, description?: string): Promise<void> => {
    await apiClient.post(`/v1/batch-steps/${stepId}/problem`, {
      issue_type_id: issueTypeId,
      title,
      description,
    });
  },
};

export const linesApi = {
  getAll: async (): Promise<Line[]> => {
    const response = await apiClient.get<ApiResponse<Line[]>>('/v1/lines');
    return response.data.data;
  },
};
