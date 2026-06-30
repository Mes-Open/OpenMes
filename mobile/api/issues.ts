import { api } from './client';
import type { ApiEnvelope, ApiPaginated, Issue, IssueStatus, IssueType } from '@/types/api';

export interface IssueFilters {
  status?: IssueStatus | IssueStatus[];
  line_id?: number;
  work_order_id?: number;
}

export const listIssues = (filters: IssueFilters = {}): Promise<Issue[]> =>
  api
    .get<ApiPaginated<Issue> | ApiEnvelope<Issue[]>>('/api/v1/issues', { params: filters })
    .then((r) => r.data.data);

export const getIssue = (id: number): Promise<Issue> =>
  api.get<ApiEnvelope<Issue>>(`/api/v1/issues/${id}`).then((r) => r.data.data);

export const createIssue = (payload: {
  issue_type_id: number;
  description?: string;
  work_order_id?: number;
  line_id?: number;
}): Promise<Issue> =>
  api.post<ApiEnvelope<Issue>>('/api/v1/issues', payload).then((r) => r.data.data);

export const acknowledgeIssue = (id: number): Promise<Issue> =>
  api.post<ApiEnvelope<Issue>>(`/api/v1/issues/${id}/acknowledge`).then((r) => r.data.data);

export const resolveIssue = (id: number, resolutionNotes?: string): Promise<Issue> =>
  api
    .post<ApiEnvelope<Issue>>(`/api/v1/issues/${id}/resolve`, { resolution_notes: resolutionNotes })
    .then((r) => r.data.data);

export const closeIssue = (id: number): Promise<Issue> =>
  api.post<ApiEnvelope<Issue>>(`/api/v1/issues/${id}/close`).then((r) => r.data.data);

export const listIssueTypes = (): Promise<IssueType[]> =>
  api.get<ApiEnvelope<IssueType[]>>('/api/v1/issue-types').then((r) => r.data.data);

export interface IssueLineStat {
  line_id: number;
  line_name?: string;
  open?: number;
  acknowledged?: number;
  total?: number;
}

export const issueStatsByLine = (): Promise<IssueLineStat[]> =>
  api.get<ApiEnvelope<IssueLineStat[]>>('/api/v1/issues/stats/line').then((r) => r.data.data);
