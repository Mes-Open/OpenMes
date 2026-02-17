import { Alert, Badge, Group, Text, Button, Stack } from '@mantine/core';
import { IconWifiOff, IconRefresh } from '@tabler/icons-react';
import { useNetworkStatus } from '../../hooks/useNetworkStatus';
import { useOfflineSync } from '../../hooks/useOfflineSync';
import { useOfflineQueueStore } from '../../stores/offlineQueueStore';

export function OfflineIndicator() {
  const { isOnline } = useNetworkStatus();
  const { processQueue, isSyncing, queuedCount } = useOfflineSync();
  const { failedActions } = useOfflineQueueStore();

  // Don't show anything if online and no pending/failed actions
  if (isOnline && queuedCount === 0 && failedActions.length === 0) {
    return null;
  }

  // Show offline status
  if (!isOnline) {
    return (
      <Alert
        icon={<IconWifiOff size={16} />}
        title="Offline Mode"
        color="yellow"
        style={{
          position: 'fixed',
          top: 16,
          right: 16,
          zIndex: 1000,
          maxWidth: 400,
        }}
      >
        <Stack gap="xs">
          <Text size="sm">
            You are currently offline. Actions will be queued and synced when connection is
            restored.
          </Text>
          {queuedCount > 0 && (
            <Group gap="xs">
              <Badge color="yellow" size="sm">
                {queuedCount} queued action{queuedCount !== 1 ? 's' : ''}
              </Badge>
            </Group>
          )}
        </Stack>
      </Alert>
    );
  }

  // Show syncing status
  if (isSyncing) {
    return (
      <Alert
        icon={<IconRefresh size={16} />}
        title="Syncing..."
        color="blue"
        style={{
          position: 'fixed',
          top: 16,
          right: 16,
          zIndex: 1000,
          maxWidth: 400,
        }}
      >
        <Text size="sm">Syncing {queuedCount} queued action(s)...</Text>
      </Alert>
    );
  }

  // Show failed actions if any
  if (failedActions.length > 0) {
    return (
      <Alert
        icon={<IconWifiOff size={16} />}
        title="Sync Failed"
        color="red"
        style={{
          position: 'fixed',
          top: 16,
          right: 16,
          zIndex: 1000,
          maxWidth: 400,
        }}
      >
        <Stack gap="xs">
          <Text size="sm">
            {failedActions.length} action(s) failed to sync. Please try again.
          </Text>
          <Button size="xs" variant="light" onClick={processQueue}>
            Retry Sync
          </Button>
        </Stack>
      </Alert>
    );
  }

  // Show pending actions waiting to sync
  if (queuedCount > 0) {
    return (
      <Alert
        icon={<IconRefresh size={16} />}
        title="Pending Sync"
        color="blue"
        style={{
          position: 'fixed',
          top: 16,
          right: 16,
          zIndex: 1000,
          maxWidth: 400,
        }}
      >
        <Stack gap="xs">
          <Text size="sm">{queuedCount} action(s) waiting to sync.</Text>
          <Button size="xs" variant="light" onClick={processQueue} loading={isSyncing}>
            Sync Now
          </Button>
        </Stack>
      </Alert>
    );
  }

  return null;
}
