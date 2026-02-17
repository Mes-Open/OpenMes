import apiClient from './client';

export interface IssueType {
  id: number;
  code: string;
  name: string;
  severity: 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
  is_blocking: boolean;
  is_active: boolean;
}

export interface Issue {
  id: number;
  work_order_id: number;
  batch_step_id?: number;
  issue_type_id: number;
  title: string;
  description?: string;
  status: 'OPEN' | 'ACKNOWLEDGED' | 'RESOLVED' | 'CLOSED';
  reported_by_id: number;
  assigned_to_id?: number;
  reported_at: string;
  acknowledged_at?: string;
  resolved_at?: string;
  closed_at?: string;
  resolution_notes?: string;
  issue_type?: IssueType;
  reported_by?: { id: number; username: string };
  assigned_to?: { id: number; username: string };
}

export interface CreateIssueData {
  work_order_id: number;
  batch_step_id?: number;
  issue_type_id: number;
  title: string;
  description?: string;
}

export interface ResolveIssueData {
  resolution_notes: string;
}

export const issuesApi = {
  // Get all issues (with filters)
  getIssues: async (params?: {
    line_id?: number;
    work_order_id?: number;
    status?: string;
  }) => {
    const response = await apiClient.get('/v1/issues', { params });
    return response.data;
  },

  // Get a single issue
  getIssue: async (id: number) => {
    const response = await apiClient.get(`/v1/issues/${id}`);
    return response.data.data as Issue;
  },

  // Create a new issue
  createIssue: async (data: CreateIssueData) => {
    const response = await apiClient.post('/v1/issues', data);
    return response.data.data as Issue;
  },

  // Update an issue
  updateIssue: async (id: number, data: Partial<Issue>) => {
    const response = await apiClient.patch(`/v1/issues/${id}`, data);
    return response.data.data as Issue;
  },

  // Acknowledge an issue
  acknowledgeIssue: async (id: number) => {
    const response = await apiClient.post(`/v1/issues/${id}/acknowledge`);
    return response.data.data as Issue;
  },

  // Resolve an issue
  resolveIssue: async (id: number, data: ResolveIssueData) => {
    const response = await apiClient.post(`/v1/issues/${id}/resolve`, data);
    return response.data.data as Issue;
  },

  // Close an issue
  closeIssue: async (id: number) => {
    const response = await apiClient.post(`/v1/issues/${id}/close`);
    return response.data.data as Issue;
  },

  // Get issue statistics for a line
  getLineStats: async (lineId: number) => {
    const response = await apiClient.get('/v1/issues/stats/line', {
      params: { line_id: lineId },
    });
    return response.data.data;
  },

  // Get all issue types
  getIssueTypes: async (includeInactive = false) => {
    const response = await apiClient.get('/v1/issue-types', {
      params: { include_inactive: includeInactive },
    });
    return response.data.data as IssueType[];
  },

  // Report a problem on a batch step (creates an issue)
  reportProblem: async (
    batchStepId: number,
    data: {
      issue_type_id: number;
      title: string;
      description?: string;
    }
  ) => {
    const response = await apiClient.post(`/v1/batch-steps/${batchStepId}/problem`, data);
    return response.data.data;
  },
};
