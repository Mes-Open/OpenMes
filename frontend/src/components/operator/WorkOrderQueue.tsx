import { Card, Text, Stack, Badge, Button, Title, Loader, Group } from '@mantine/core';
import { useNavigate } from 'react-router-dom';
import { useWorkOrders } from '../../hooks/useWorkOrders';
import { useAuthStore } from '../../stores/authStore';
import type { WorkOrder } from '../../types';

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

export const WorkOrderQueue = () => {
  const navigate = useNavigate();
  const { selectedLineId } = useAuthStore();
  const { data: workOrders, isLoading, error } = useWorkOrders({ line_id: selectedLineId || undefined });

  if (isLoading) {
    return (
      <Stack align="center" justify="center" style={{ minHeight: '50vh' }}>
        <Loader size="lg" />
        <Text>Loading work orders...</Text>
      </Stack>
    );
  }

  if (error) {
    return (
      <Stack align="center" justify="center" style={{ minHeight: '50vh' }}>
        <Text c="red">Failed to load work orders</Text>
      </Stack>
    );
  }

  const activeWorkOrders = workOrders?.filter((wo) => wo.status !== 'DONE' && wo.status !== 'CANCELLED') || [];
  const completedWorkOrders = workOrders?.filter((wo) => wo.status === 'DONE' || wo.status === 'CANCELLED') || [];

  return (
    <Stack gap="lg" style={{ padding: '1rem' }}>
      <Title order={2}>Work Order Queue</Title>

      {activeWorkOrders.length === 0 && (
        <Card shadow="sm" padding="lg" radius="md" withBorder>
          <Text c="dimmed" ta="center">
            No active work orders
          </Text>
        </Card>
      )}

      <Stack gap="md">
        {activeWorkOrders.map((workOrder: WorkOrder) => (
          <Card key={workOrder.id} shadow="sm" padding="lg" radius="md" withBorder>
            <Stack gap="sm">
              <Group justify="space-between">
                <Text fw={700} size="lg">
                  {workOrder.order_no}
                </Text>
                <Badge color={getStatusColor(workOrder.status)} size="lg">
                  {workOrder.status}
                </Badge>
              </Group>

              <Text size="sm">Product: {workOrder.product_type.name}</Text>

              <Group gap="xs">
                <Text size="sm" c="dimmed">
                  Planned: {workOrder.planned_qty} {workOrder.product_type.unit_of_measure}
                </Text>
                <Text size="sm" c="dimmed">
                  •
                </Text>
                <Text size="sm" c="dimmed">
                  Produced: {workOrder.produced_qty} {workOrder.product_type.unit_of_measure}
                </Text>
              </Group>

              {workOrder.due_date && (
                <Text size="sm" c="dimmed">
                  Due: {new Date(workOrder.due_date).toLocaleDateString()}
                </Text>
              )}

              <Button onClick={() => navigate(`/operator/work-order/${workOrder.id}`)} fullWidth size="md" mt="sm">
                View Details
              </Button>
            </Stack>
          </Card>
        ))}
      </Stack>

      {completedWorkOrders.length > 0 && (
        <>
          <Title order={3} mt="xl">
            Completed
          </Title>
          <Stack gap="md">
            {completedWorkOrders.slice(0, 5).map((workOrder: WorkOrder) => (
              <Card key={workOrder.id} shadow="sm" padding="md" radius="md" withBorder style={{ opacity: 0.7 }}>
                <Group justify="space-between">
                  <Text fw={500}>{workOrder.order_no}</Text>
                  <Badge color={getStatusColor(workOrder.status)}>{workOrder.status}</Badge>
                </Group>
              </Card>
            ))}
          </Stack>
        </>
      )}
    </Stack>
  );
};
