import { useMutation, useQueryClient } from '@tanstack/react-query';
import { stepsApi } from '../api/workOrders';
import { notifications } from '@mantine/notifications';

export const useStartStep = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (stepId: number) => stepsApi.start(stepId),
    onSuccess: (data) => {
      // Invalidate batch steps query
      queryClient.invalidateQueries({ queryKey: ['batches', data.batch_id, 'steps'] });

      notifications.show({
        title: 'Step Started',
        message: `${data.name} has been started`,
        color: 'blue',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Error',
        message: error.response?.data?.message || 'Failed to start step',
        color: 'red',
      });
    },
  });
};

export const useCompleteStep = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ stepId, producedQty }: { stepId: number; producedQty?: number }) =>
      stepsApi.complete(stepId, producedQty),
    onSuccess: (data) => {
      // Invalidate batch steps query and work orders
      queryClient.invalidateQueries({ queryKey: ['batches', data.batch_id, 'steps'] });
      queryClient.invalidateQueries({ queryKey: ['workOrders'] });

      notifications.show({
        title: 'Step Completed',
        message: `${data.name} has been completed`,
        color: 'green',
      });
    },
    onError: (error: any) => {
      notifications.show({
        title: 'Error',
        message: error.response?.data?.message || 'Failed to complete step',
        color: 'red',
      });
    },
  });
};

export const useReportProblem = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      stepId,
      issueTypeId,
      title,
      description,
    }: {
      stepId: number;
      issueTypeId: number;
      title: string;
      description?: string;
    }) => stepsApi.reportProblem(stepId, issueTypeId, title, description),
    onSuccess: () => {
      // Invalidate related queries
      queryClient.invalidateQueries({ queryKey: ['batches'] });
      queryClient.invalidateQueries({ queryKey: ['workOrders'] });

      notifications.show({
        title: 'Problem Reported',
        message: 'Issue has been reported successfully',
        color: 'orange',
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
