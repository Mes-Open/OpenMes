import { useEffect } from 'react';
import { useOfflineQueueStore } from '../stores/offlineQueueStore';

/**
 * Hook to monitor network status and update the offline queue store
 */
export function useNetworkStatus() {
  const { setOnlineStatus, isOnline } = useOfflineQueueStore();

  useEffect(() => {
    const handleOnline = () => {
      console.log('[NetworkStatus] Network online');
      setOnlineStatus(true);
    };

    const handleOffline = () => {
      console.log('[NetworkStatus] Network offline');
      setOnlineStatus(false);
    };

    // Set initial status
    setOnlineStatus(navigator.onLine);

    // Listen for network changes
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, [setOnlineStatus]);

  return { isOnline };
}
