import { Stack, Timeline, Text, Badge, Loader, Card } from '@mantine/core';
import {
  IconCirclePlus,
  IconPlayerPlay,
  IconCircleCheck,
  IconAlertTriangle,
  IconFileImport,
} from '@tabler/icons-react';
import { useEntityEventLogs } from '../../hooks/useAuditLogs';

const getEventIcon = (eventType: string) => {
  switch (eventType) {
    case 'WORK_ORDER_CREATED':
    case 'BATCH_CREATED':
      return <IconCirclePlus size={16} />;
    case 'STEP_STARTED':
      return <IconPlayerPlay size={16} />;
    case 'STEP_COMPLETED':
    case 'WORK_ORDER_COMPLETED':
      return <IconCircleCheck size={16} />;
    case 'ISSUE_CREATED':
      return <IconAlertTriangle size={16} />;
    case 'WORK_ORDER_IMPORTED':
      return <IconFileImport size={16} />;
    default:
      return <IconCirclePlus size={16} />;
  }
};

const getEventColor = (eventType: string) => {
  if (eventType.includes('COMPLETED')) return 'green';
  if (eventType.includes('STARTED')) return 'blue';
  if (eventType.includes('ISSUE')) return 'red';
  if (eventType.includes('IMPORTED')) return 'violet';
  return 'gray';
};

const formatEventType = (eventType: string) => {
  return eventType
    .replace(/_/g, ' ')
    .toLowerCase()
    .replace(/\b\w/g, (char) => char.toUpperCase());
};

interface EventTimelineProps {
  entityType: string;
  entityId: number;
}

export function EventTimeline({ entityType, entityId }: EventTimelineProps) {
  const { data: events, isLoading } = useEntityEventLogs(entityType, entityId);

  if (isLoading) {
    return (
      <Stack align="center" py="md">
        <Loader size="sm" />
      </Stack>
    );
  }

  if (!events || events.length === 0) {
    return (
      <Card withBorder padding="md">
        <Text c="dimmed" ta="center">No events recorded yet</Text>
      </Card>
    );
  }

  return (
    <Timeline active={events.length} bulletSize={24} lineWidth={2}>
      {events.map((event) => (
        <Timeline.Item
          key={event.id}
          bullet={getEventIcon(event.event_type)}
          title={
            <Group gap="xs">
              <Text fw={500}>{formatEventType(event.event_type)}</Text>
              <Badge size="xs" color={getEventColor(event.event_type)}>
                {event.event_type}
              </Badge>
            </Group>
          }
        >
          <Stack gap={4}>
            <Text size="sm" c="dimmed">
              {new Date(event.created_at).toLocaleString()}
              {event.user && ` by ${event.user.username}`}
            </Text>

            {event.payload && Object.keys(event.payload).length > 0 && (
              <Stack gap={2} mt="xs">
                {Object.entries(event.payload).map(([key, value]) => {
                  // Skip null or undefined values
                  if (value === null || value === undefined) return null;

                  return (
                    <Text key={key} size="xs" c="dimmed">
                      <strong>{key.replace(/_/g, ' ')}:</strong>{' '}
                      {typeof value === 'object' ? JSON.stringify(value) : String(value)}
                    </Text>
                  );
                })}
              </Stack>
            )}
          </Stack>
        </Timeline.Item>
      ))}
    </Timeline>
  );
}
