import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export interface QueuedAction {
  id: string;
  type: 'START_STEP' | 'COMPLETE_STEP' | 'CREATE_ISSUE' | 'UPDATE_BATCH';
  url: string;
  method: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
  data?: any;
  headers?: Record<string, string>;
  timestamp: number;
  retryCount: number;
  maxRetries: number;
  status: 'pending' | 'syncing' | 'failed' | 'success';
  error?: string;
}

interface OfflineQueueState {
  isOnline: boolean;
  isSyncing: boolean;
  queue: QueuedAction[];
  failedActions: QueuedAction[];

  // Actions
  setOnlineStatus: (online: boolean) => void;
  addToQueue: (action: Omit<QueuedAction, 'id' | 'timestamp' | 'retryCount' | 'status'>) => void;
  removeFromQueue: (id: string) => void;
  markActionAsSuccess: (id: string) => void;
  markActionAsFailed: (id: string, error: string) => void;
  incrementRetry: (id: string) => void;
  clearQueue: () => void;
  clearFailedActions: () => void;
  startSync: () => void;
  endSync: () => void;
  getNextPendingAction: () => QueuedAction | undefined;
}

export const useOfflineQueueStore = create<OfflineQueueState>()(
  persist(
    (set, get) => ({
      isOnline: navigator.onLine,
      isSyncing: false,
      queue: [],
      failedActions: [],

      setOnlineStatus: (online) => {
        set({ isOnline: online });

        // Auto-trigger sync when coming back online
        if (online && get().queue.length > 0 && !get().isSyncing) {
          // Trigger sync (will be handled by background sync hook)
          console.log('[OfflineQueue] Network restored, triggering sync...');
        }
      },

      addToQueue: (actionData) => {
        const action: QueuedAction = {
          ...actionData,
          id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
          timestamp: Date.now(),
          retryCount: 0,
          status: 'pending',
        };

        set((state) => ({
          queue: [...state.queue, action],
        }));

        console.log('[OfflineQueue] Action queued:', action.type, action.id);
      },

      removeFromQueue: (id) => {
        set((state) => ({
          queue: state.queue.filter((action) => action.id !== id),
        }));
      },

      markActionAsSuccess: (id) => {
        const action = get().queue.find((a) => a.id === id);
        if (action) {
          console.log('[OfflineQueue] Action succeeded:', action.type, id);
          get().removeFromQueue(id);
        }
      },

      markActionAsFailed: (id, error) => {
        set((state) => {
          const action = state.queue.find((a) => a.id === id);
          if (!action) return state;

          const updatedAction = {
            ...action,
            status: 'failed' as const,
            error,
          };

          return {
            queue: state.queue.filter((a) => a.id !== id),
            failedActions: [...state.failedActions, updatedAction],
          };
        });

        console.error('[OfflineQueue] Action failed:', id, error);
      },

      incrementRetry: (id) => {
        set((state) => ({
          queue: state.queue.map((action) =>
            action.id === id
              ? { ...action, retryCount: action.retryCount + 1 }
              : action
          ),
        }));
      },

      clearQueue: () => {
        set({ queue: [] });
      },

      clearFailedActions: () => {
        set({ failedActions: [] });
      },

      startSync: () => {
        set({ isSyncing: true });
      },

      endSync: () => {
        set({ isSyncing: false });
      },

      getNextPendingAction: () => {
        const { queue } = get();
        return queue.find((action) => action.status === 'pending');
      },
    }),
    {
      name: 'offline-queue-storage',
      partialize: (state) => ({
        queue: state.queue,
        failedActions: state.failedActions,
      }),
    }
  )
);
