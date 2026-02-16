import { useParams, useNavigate } from 'react-router-dom';
import { Stack, Title, Text, Badge, Card, Button, Group, Loader, Accordion } from '@mantine/core';
import { useWorkOrder, useBatches, useCreateBatch } from '../../hooks/useWorkOrders';
import { BatchStepList } from './BatchStepList';
import type { Batch } from '../../types';

const getStatusColor = (status: string) => {
  switch (status) {
    case 'PENDING':
      return 'gray';
    case 'IN_PROGRESS':
      return 'blue';
    case 'BLOCKED':
      return 'red';
    case 'DONE':
      return 'green';
    case 'CANCELLED':
      return 'dark';
    default:
      return 'gray';
  }
};

export const WorkOrderDetail = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const workOrderId = parseInt(id || '0');

  const { data: workOrder, isLoading: isLoadingWO } = useWorkOrder(workOrderId);
  const { data: batches, isLoading: isLoadingBatches } = useBatches(workOrderId);
  const createBatch = useCreateBatch();

  const handleCreateBatch = () => {
    if (!workOrder) return;

    const remainingQty = workOrder.planned_qty - workOrder.produced_qty;
    const targetQty = Math.min(remainingQty, 50); // Default batch size or remaining

    createBatch.mutate({ workOrderId, targetQty });
  };

  if (isLoadingWO || isLoadingBatches) {
    return (
      <Stack align="center" justify="center" style={{ minHeight: '50vh' }}>
        <Loader size="lg" />
        <Text>Loading work order...</Text>
      </Stack>
    );
  }

  if (!workOrder) {
    return (
      <Stack align="center" justify="center" style={{ minHeight: '50vh' }}>
        <Text c="red">Work order not found</Text>
        <Button onClick={() => navigate('/operator/queue')}>Back to Queue</Button>
      </Stack>
    );
  }

  const activeBatches = batches?.filter((b) => b.status !== 'DONE' && b.status !== 'CANCELLED') || [];
  const completedBatches = batches?.filter((b) => b.status === 'DONE' || b.status === 'CANCELLED') || [];
  const canCreateBatch = workOrder.produced_qty < workOrder.planned_qty && workOrder.status !== 'BLOCKED';

  return (
    <Stack gap="lg" style={{ padding: '1rem' }}>
      <Group>
        <Button variant="subtle" onClick={() => navigate('/operator/queue')}>
          ← Back to Queue
        </Button>
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder>
        <Stack gap="sm">
          <Group justify="space-between">
            <Title order={2}>{workOrder.order_no}</Title>
            <Badge color={getStatusColor(workOrder.status)} size="lg">
              {workOrder.status}
            </Badge>
          </Group>

          <Text size="lg" fw={500}>
            {workOrder.product_type.name}
          </Text>

          <Group gap="xl">
            <div>
              <Text size="sm" c="dimmed">
                Planned Quantity
              </Text>
              <Text size="lg" fw={600}>
                {workOrder.planned_qty} {workOrder.product_type.unit_of_measure}
              </Text>
            </div>
            <div>
              <Text size="sm" c="dimmed">
                Produced Quantity
              </Text>
              <Text size="lg" fw={600} c={workOrder.produced_qty >= workOrder.planned_qty ? 'green' : 'blue'}>
                {workOrder.produced_qty} {workOrder.product_type.unit_of_measure}
              </Text>
            </div>
            <div>
              <Text size="sm" c="dimmed">
                Remaining
              </Text>
              <Text size="lg" fw={600}>
                {workOrder.planned_qty - workOrder.produced_qty} {workOrder.product_type.unit_of_measure}
              </Text>
            </div>
          </Group>

          {workOrder.due_date && (
            <Text size="sm" c="dimmed">
              Due Date: {new Date(workOrder.due_date).toLocaleDateString()}
            </Text>
          )}

          {workOrder.description && (
            <Text size="sm" c="dimmed">
              {workOrder.description}
            </Text>
          )}
        </Stack>
      </Card>

      <Card shadow="sm" padding="lg" radius="md" withBorder>
        <Stack gap="md">
          <Group justify="space-between">
            <Title order={3}>Batches</Title>
            {canCreateBatch && (
              <Button onClick={handleCreateBatch} loading={createBatch.isPending}>
                Create New Batch
              </Button>
            )}
          </Group>

          {activeBatches.length === 0 && completedBatches.length === 0 && (
            <Text c="dimmed" ta="center">
              No batches created yet. Create a batch to start production.
            </Text>
          )}

          {activeBatches.length > 0 && (
            <Accordion variant="separated">
              {activeBatches.map((batch: Batch) => (
                <Accordion.Item key={batch.id} value={batch.id.toString()}>
                  <Accordion.Control>
                    <Group justify="space-between" style={{ width: '100%', paddingRight: '1rem' }}>
                      <Text fw={500}>
                        Batch #{batch.batch_number} - {batch.target_qty} {workOrder.product_type.unit_of_measure}
                      </Text>
                      <Badge color={getStatusColor(batch.status)}>{batch.status}</Badge>
                    </Group>
                  </Accordion.Control>
                  <Accordion.Panel>
                    <BatchStepList batchId={batch.id} />
                  </Accordion.Panel>
                </Accordion.Item>
              ))}
            </Accordion>
          )}

          {completedBatches.length > 0 && (
            <>
              <Title order={4} mt="md">
                Completed Batches
              </Title>
              <Stack gap="xs">
                {completedBatches.map((batch: Batch) => (
                  <Card key={batch.id} padding="sm" withBorder style={{ opacity: 0.7 }}>
                    <Group justify="space-between">
                      <Text size="sm">
                        Batch #{batch.batch_number} - {batch.produced_qty}/{batch.target_qty}{' '}
                        {workOrder.product_type.unit_of_measure}
                      </Text>
                      <Badge color={getStatusColor(batch.status)} size="sm">
                        {batch.status}
                      </Badge>
                    </Group>
                  </Card>
                ))}
              </Stack>
            </>
          )}
        </Stack>
      </Card>
    </Stack>
  );
};
