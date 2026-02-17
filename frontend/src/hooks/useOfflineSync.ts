import { useEffect, useCallback } from 'react';
import { useOfflineQueueStore } from '../stores/offlineQueueStore';
import { apiClient } from '../api/client';
import { notifications } from '@mantine/notifications';

/**
 * Hook to automatically sync queued actions when network comes back online
 */
export function useOfflineSync() {
  const {
    isOnline,
    isSyncing,
    queue,
    startSync,
    endSync,
    getNextPendingAction,
    markActionAsSuccess,
    markActionAsFailed,
    incrementRetry,
  } = useOfflineQueueStore();

  const processQueue = useCallback(async () => {
    if (!isOnline || isSyncing || queue.length === 0) {
      return;
    }

    console.log('[OfflineSync] Starting sync of', queue.length, 'actions');
    startSync();

    let successCount = 0;
    let failureCount = 0;

    try {
      // Process actions sequentially to maintain order
      let nextAction = getNextPendingAction();

      while (nextAction) {
        const { id, url, method, data, headers, retryCount, maxRetries } = nextAction;

        try {
          console.log('[OfflineSync] Processing:', nextAction.type, id);

          // Make the API request
          await apiClient({
            url,
            method,
            data,
            headers,
          });

          markActionAsSuccess(id);
          successCount++;
        } catch (error: any) {
          console.error('[OfflineSync] Failed to sync action:', id, error);

          // Check if we should retry
          if (retryCount < maxRetries) {
            incrementRetry(id);
            console.log(`[OfflineSync] Will retry action ${id} (attempt ${retryCount + 1}/${maxRetries})`);
          } else {
            markActionAsFailed(id, error.message || 'Failed to sync action');
            failureCount++;
          }
        }

        // Get next pending action
        nextAction = getNextPendingAction();
      }

      // Show notification
      if (successCount > 0) {
        notifications.show({
          title: 'Sync Complete',
          message: `${successCount} action(s) synced successfully${failureCount > 0 ? `, ${failureCount} failed` : ''}`,
          color: failureCount > 0 ? 'yellow' : 'green',
        });
      } else if (failureCount > 0) {
        notifications.show({
          title: 'Sync Failed',
          message: `${failureCount} action(s) could not be synced`,
          color: 'red',
        });
      }
    } finally {
      endSync();
      console.log('[OfflineSync] Sync complete:', successCount, 'succeeded,', failureCount, 'failed');
    }
  }, [
    isOnline,
    isSyncing,
    queue,
    startSync,
    endSync,
    getNextPendingAction,
    markActionAsSuccess,
    markActionAsFailed,
    incrementRetry,
  ]);

  // Auto-trigger sync when coming back online
  useEffect(() => {
    if (isOnline && queue.length > 0 && !isSyncing) {
      // Delay slightly to ensure network is stable
      const timer = setTimeout(() => {
        processQueue();
      }, 1000);

      return () => clearTimeout(timer);
    }
  }, [isOnline, queue.length, isSyncing, processQueue]);

  return {
    processQueue,
    isSyncing,
    queuedCount: queue.length,
  };
}
