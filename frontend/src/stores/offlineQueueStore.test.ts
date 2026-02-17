import { describe, it, expect, beforeEach } from 'vitest';
import { useOfflineQueueStore } from './offlineQueueStore';

describe('offlineQueueStore', () => {
  beforeEach(() => {
    // Reset store before each test
    const store = useOfflineQueueStore.getState();
    store.clearQueue();
    store.clearFailedActions();
  });

  describe('addToQueue', () => {
    it('should add an action to the queue', () => {
      const { addToQueue, queue } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        data: { started_by_id: 1 },
        maxRetries: 3,
      });

      const currentQueue = useOfflineQueueStore.getState().queue;
      expect(currentQueue).toHaveLength(1);
      expect(currentQueue[0].type).toBe('START_STEP');
      expect(currentQueue[0].url).toBe('/api/v1/batch-steps/1/start');
      expect(currentQueue[0].status).toBe('pending');
      expect(currentQueue[0].retryCount).toBe(0);
    });

    it('should generate unique IDs for queued actions', () => {
      const { addToQueue } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        maxRetries: 3,
      });

      addToQueue({
        type: 'COMPLETE_STEP',
        url: '/api/v1/batch-steps/1/complete',
        method: 'POST',
        maxRetries: 3,
      });

      const currentQueue = useOfflineQueueStore.getState().queue;
      expect(currentQueue).toHaveLength(2);
      expect(currentQueue[0].id).not.toBe(currentQueue[1].id);
    });
  });

  describe('removeFromQueue', () => {
    it('should remove an action from the queue by ID', () => {
      const { addToQueue, removeFromQueue } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        maxRetries: 3,
      });

      const actionId = useOfflineQueueStore.getState().queue[0].id;
      removeFromQueue(actionId);

      const currentQueue = useOfflineQueueStore.getState().queue;
      expect(currentQueue).toHaveLength(0);
    });
  });

  describe('markActionAsSuccess', () => {
    it('should remove action from queue when marked as success', () => {
      const { addToQueue, markActionAsSuccess } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        maxRetries: 3,
      });

      const actionId = useOfflineQueueStore.getState().queue[0].id;
      markActionAsSuccess(actionId);

      const currentQueue = useOfflineQueueStore.getState().queue;
      expect(currentQueue).toHaveLength(0);
    });
  });

  describe('markActionAsFailed', () => {
    it('should move action to failed actions list', () => {
      const { addToQueue, markActionAsFailed } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        maxRetries: 3,
      });

      const actionId = useOfflineQueueStore.getState().queue[0].id;
      markActionAsFailed(actionId, 'Network timeout');

      const state = useOfflineQueueStore.getState();
      expect(state.queue).toHaveLength(0);
      expect(state.failedActions).toHaveLength(1);
      expect(state.failedActions[0].status).toBe('failed');
      expect(state.failedActions[0].error).toBe('Network timeout');
    });
  });

  describe('incrementRetry', () => {
    it('should increment retry count for an action', () => {
      const { addToQueue, incrementRetry } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        maxRetries: 3,
      });

      const actionId = useOfflineQueueStore.getState().queue[0].id;
      incrementRetry(actionId);

      const currentQueue = useOfflineQueueStore.getState().queue;
      expect(currentQueue[0].retryCount).toBe(1);

      incrementRetry(actionId);
      expect(useOfflineQueueStore.getState().queue[0].retryCount).toBe(2);
    });
  });

  describe('getNextPendingAction', () => {
    it('should return the first pending action', () => {
      const { addToQueue, getNextPendingAction } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        maxRetries: 3,
      });

      addToQueue({
        type: 'COMPLETE_STEP',
        url: '/api/v1/batch-steps/1/complete',
        method: 'POST',
        maxRetries: 3,
      });

      const nextAction = getNextPendingAction();
      expect(nextAction).toBeDefined();
      expect(nextAction?.type).toBe('START_STEP');
    });

    it('should return undefined when queue is empty', () => {
      const { getNextPendingAction } = useOfflineQueueStore.getState();
      const nextAction = getNextPendingAction();
      expect(nextAction).toBeUndefined();
    });
  });

  describe('sync status', () => {
    it('should track syncing status', () => {
      const { startSync, endSync } = useOfflineQueueStore.getState();

      expect(useOfflineQueueStore.getState().isSyncing).toBe(false);

      startSync();
      expect(useOfflineQueueStore.getState().isSyncing).toBe(true);

      endSync();
      expect(useOfflineQueueStore.getState().isSyncing).toBe(false);
    });
  });

  describe('clearQueue', () => {
    it('should remove all actions from queue', () => {
      const { addToQueue, clearQueue } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        maxRetries: 3,
      });

      addToQueue({
        type: 'COMPLETE_STEP',
        url: '/api/v1/batch-steps/2/complete',
        method: 'POST',
        maxRetries: 3,
      });

      expect(useOfflineQueueStore.getState().queue).toHaveLength(2);

      clearQueue();
      expect(useOfflineQueueStore.getState().queue).toHaveLength(0);
    });
  });

  describe('clearFailedActions', () => {
    it('should remove all failed actions', () => {
      const { addToQueue, markActionAsFailed, clearFailedActions } = useOfflineQueueStore.getState();

      addToQueue({
        type: 'START_STEP',
        url: '/api/v1/batch-steps/1/start',
        method: 'POST',
        maxRetries: 3,
      });

      const actionId = useOfflineQueueStore.getState().queue[0].id;
      markActionAsFailed(actionId, 'Test error');

      expect(useOfflineQueueStore.getState().failedActions).toHaveLength(1);

      clearFailedActions();
      expect(useOfflineQueueStore.getState().failedActions).toHaveLength(0);
    });
  });
});
