import { Stack, Card, Text, Badge, Group, Button, Loader } from '@mantine/core';
import { useBatchSteps } from '../../hooks/useWorkOrders';
import { useStartStep, useCompleteStep } from '../../hooks/useSteps';
import type { BatchStep } from '../../types';

const getStatusColor = (status: string) => {
  switch (status) {
    case 'PENDING':
      return 'gray';
    case 'IN_PROGRESS':
      return 'blue';
    case 'DONE':
      return 'green';
    case 'SKIPPED':
      return 'orange';
    default:
      return 'gray';
  }
};

interface BatchStepListProps {
  batchId: number;
}

export const BatchStepList = ({ batchId }: BatchStepListProps) => {
  const { data: steps, isLoading } = useBatchSteps(batchId);
  const startStep = useStartStep();
  const completeStep = useCompleteStep();

  if (isLoading) {
    return (
      <Stack align="center" py="md">
        <Loader size="sm" />
      </Stack>
    );
  }

  if (!steps || steps.length === 0) {
    return <Text c="dimmed">No steps available</Text>;
  }

  const handleStart = (stepId: number) => {
    startStep.mutate(stepId);
  };

  const handleComplete = (stepId: number) => {
    completeStep.mutate({ stepId });
  };

  return (
    <Stack gap="md">
      {steps.map((step: BatchStep) => {
        const canStart = step.status === 'PENDING';
        const canComplete = step.status === 'IN_PROGRESS';
        const isProcessing = startStep.isPending || completeStep.isPending;

        return (
          <Card key={step.id} padding="md" withBorder>
            <Stack gap="sm">
              <Group justify="space-between">
                <Text fw={500}>
                  Step {step.step_number}: {step.name}
                </Text>
                <Badge color={getStatusColor(step.status)}>{step.status}</Badge>
              </Group>

              {step.instruction && (
                <Text size="sm" c="dimmed">
                  {step.instruction}
                </Text>
              )}

              {step.status === 'IN_PROGRESS' && step.started_at && (
                <Text size="xs" c="dimmed">
                  Started: {new Date(step.started_at).toLocaleString()}
                  {step.started_by && ` by ${step.started_by.username}`}
                </Text>
              )}

              {step.status === 'DONE' && step.completed_at && (
                <Group gap="xs">
                  <Text size="xs" c="dimmed">
                    Completed: {new Date(step.completed_at).toLocaleString()}
                  </Text>
                  {step.duration_minutes && (
                    <Text size="xs" c="dimmed">
                      • Duration: {step.duration_minutes} min
                    </Text>
                  )}
                </Group>
              )}

              <Group gap="xs">
                {canStart && (
                  <Button onClick={() => handleStart(step.id)} disabled={isProcessing} color="blue" size="md" fullWidth>
                    START
                  </Button>
                )}
                {canComplete && (
                  <Button
                    onClick={() => handleComplete(step.id)}
                    disabled={isProcessing}
                    color="green"
                    size="md"
                    fullWidth
                  >
                    COMPLETE
                  </Button>
                )}
                {step.status === 'DONE' && (
                  <Text c="green" fw={500} ta="center" style={{ width: '100%' }}>
                    ✓ Completed
                  </Text>
                )}
              </Group>
            </Stack>
          </Card>
        );
      })}
    </Stack>
  );
};
