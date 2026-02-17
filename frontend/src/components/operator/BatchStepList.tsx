import { useState } from 'react';
import { Stack, Card, Text, Badge, Group, Button, Loader } from '@mantine/core';
import { useBatchSteps } from '../../hooks/useWorkOrders';
import { useStartStep, useCompleteStep } from '../../hooks/useSteps';
import { IssueReportForm } from './IssueReportForm';
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
  const [issueModalOpened, setIssueModalOpened] = useState(false);
  const [selectedStep, setSelectedStep] = useState<BatchStep | null>(null);

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

  const handleReportProblem = (step: BatchStep) => {
    setSelectedStep(step);
    setIssueModalOpened(true);
  };

  return (
    <Stack gap="md">
      {steps.map((step: BatchStep) => {
        const canStart = step.status === 'PENDING';
        const canComplete = step.status === 'IN_PROGRESS';
        const isProcessing = startStep.isPending || completeStep.isPending;

        return (
          <Card key={step.id} padding="lg" withBorder className="step-card">
            <Stack gap="md">
              <Group justify="space-between" wrap="nowrap">
                <Text fw={600} size="lg">
                  Step {step.step_number}: {step.name}
                </Text>
                <Badge color={getStatusColor(step.status)} size="lg">
                  {step.status}
                </Badge>
              </Group>

              {step.instruction && (
                <Text size="md" c="dimmed" style={{ lineHeight: 1.6 }}>
                  {step.instruction}
                </Text>
              )}

              {step.status === 'IN_PROGRESS' && step.started_at && (
                <Text size="sm" c="dimmed">
                  Started: {new Date(step.started_at).toLocaleString()}
                  {step.started_by && ` by ${step.started_by.username}`}
                </Text>
              )}

              {step.status === 'DONE' && step.completed_at && (
                <Group gap="xs">
                  <Text size="sm" c="dimmed">
                    Completed: {new Date(step.completed_at).toLocaleString()}
                  </Text>
                  {step.duration_minutes && (
                    <Text size="sm" c="dimmed">
                      • Duration: {step.duration_minutes} min
                    </Text>
                  )}
                </Group>
              )}

              <Group gap="md" className="step-buttons-group touch-action-buttons">
                {canStart && (
                  <Button
                    onClick={() => handleStart(step.id)}
                    disabled={isProcessing}
                    color="blue"
                    size="lg"
                    fullWidth
                    style={{ minHeight: '56px', fontSize: '18px', fontWeight: 600 }}
                  >
                    START
                  </Button>
                )}
                {canComplete && (
                  <>
                    <Button
                      onClick={() => handleComplete(step.id)}
                      disabled={isProcessing}
                      color="green"
                      size="lg"
                      style={{ flex: 1, minHeight: '56px', fontSize: '18px', fontWeight: 600 }}
                    >
                      COMPLETE
                    </Button>
                    <Button
                      onClick={() => handleReportProblem(step)}
                      disabled={isProcessing}
                      color="orange"
                      size="lg"
                      style={{ flex: 1, minHeight: '56px', fontSize: '18px', fontWeight: 600 }}
                    >
                      PROBLEM
                    </Button>
                  </>
                )}
                {step.status === 'DONE' && (
                  <Text c="green" fw={600} ta="center" size="xl" style={{ width: '100%' }}>
                    ✓ Completed
                  </Text>
                )}
              </Group>
            </Stack>
          </Card>
        );
      })}

      {selectedStep && (
        <IssueReportForm
          opened={issueModalOpened}
          onClose={() => {
            setIssueModalOpened(false);
            setSelectedStep(null);
          }}
          batchStepId={selectedStep.id}
          stepName={`Step ${selectedStep.step_number}: ${selectedStep.name}`}
        />
      )}
    </Stack>
  );
};
