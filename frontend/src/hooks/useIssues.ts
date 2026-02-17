import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { issuesApi, CreateIssueData, ResolveIssueData } from '../api/issues';
import { notifications } from '@mantine/notifications';

// Get issues with filters
export const useIssues = (params?: {
  line_id?: number;
  work_order_id?: number;
  status?: string;
}) => {
  return useQuery({
    queryKey: ['issues', params],
    queryFn: () => issuesApi.getIssues(params),
    refetchInterval: 30000, // Refetch every 30 seconds
  });
};

// Get a single issue
export const useIssue = (id: number) => {
  return useQuery({
    queryKey: ['issues', id],
    queryFn: () => issuesApi.getIssue(id),
    enabled: !!id,
  });
};

// Get issue types
export const useIssueTypes = () => {
  return useQuery({
    queryKey: ['issueTypes'],
    queryFn: () => issuesApi.getIssueTypes(false),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });
};

// Create an issue
export const useCreateIssue = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: CreateIssueData) => issuesApi.createIssue(data),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['issues'] });
      queryClient.invalidateQueries({ queryKey: ['workOrders'] });

      notifications.show({
        title: 'Issue Reported',
        message: data.issue_type?.is_blocking
          ? 'Work order has been blocked due to this issue'
          : 'Issue has been reported to supervisors',
        color: data.issue_type?.is_blocking ? 'red' : 'yellow',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Error',
        message: error.response?.data?.message || 'Failed to report issue',
        color: 'red',
      });
    },
  });
};

// Report a problem on a batch step
export const useReportProblem = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      batchStepId,
      data,
    }: {
      batchStepId: number;
      data: { issue_type_id: number; title: string; description?: string };
    }) => issuesApi.reportProblem(batchStepId, data),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['issues'] });
      queryClient.invalidateQueries({ queryKey: ['workOrders'] });
      queryClient.invalidateQueries({ queryKey: ['batches'] });

      notifications.show({
        title: 'Problem Reported',
        message: data.work_order_blocked
          ? 'Work order has been blocked. A supervisor will address this issue.'
          : 'Issue has been reported to supervisors',
        color: data.work_order_blocked ? 'red' : 'yellow',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Error',
        message: error.response?.data?.message || 'Failed to report problem',
        color: 'red',
      });
    },
  });
};

// Acknowledge an issue
export const useAcknowledgeIssue = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (issueId: number) => issuesApi.acknowledgeIssue(issueId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['issues'] });

      notifications.show({
        title: 'Issue Acknowledged',
        message: 'You have been assigned to this issue',
        color: 'blue',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Error',
        message: error.response?.data?.message || 'Failed to acknowledge issue',
        color: 'red',
      });
    },
  });
};

// Resolve an issue
export const useResolveIssue = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ issueId, data }: { issueId: number; data: ResolveIssueData }) =>
      issuesApi.resolveIssue(issueId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['issues'] });
      queryClient.invalidateQueries({ queryKey: ['workOrders'] });

      notifications.show({
        title: 'Issue Resolved',
        message: 'The issue has been marked as resolved',
        color: 'green',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Error',
        message: error.response?.data?.message || 'Failed to resolve issue',
        color: 'red',
      });
    },
  });
};

// Close an issue
export const useCloseIssue = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (issueId: number) => issuesApi.closeIssue(issueId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['issues'] });

      notifications.show({
        title: 'Issue Closed',
        message: 'The issue has been closed',
        color: 'green',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Error',
        message: error.response?.data?.message || 'Failed to close issue',
        color: 'red',
      });
    },
  });
};

// Get line issue stats
export const useLineIssueStats = (lineId: number | undefined) => {
  return useQuery({
    queryKey: ['issueStats', lineId],
    queryFn: () => (lineId ? issuesApi.getLineStats(lineId) : null),
    enabled: !!lineId,
    refetchInterval: 30000,
  });
};
