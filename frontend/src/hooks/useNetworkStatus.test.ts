import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useNetworkStatus } from './useNetworkStatus';
import { useOfflineQueueStore } from '../stores/offlineQueueStore';

describe('useNetworkStatus', () => {
  let onlineListener: ((event: Event) => void) | null = null;
  let offlineListener: ((event: Event) => void) | null = null;

  beforeEach(() => {
    // Mock navigator.onLine
    Object.defineProperty(navigator, 'onLine', {
      writable: true,
      value: true,
    });

    // Mock addEventListener
    const originalAddEventListener = window.addEventListener;
    window.addEventListener = vi.fn((event, handler) => {
      if (event === 'online') {
        onlineListener = handler as (event: Event) => void;
      } else if (event === 'offline') {
        offlineListener = handler as (event: Event) => void;
      }
      originalAddEventListener.call(window, event, handler);
    });

    // Reset store
    const store = useOfflineQueueStore.getState();
    store.setOnlineStatus(true);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    onlineListener = null;
    offlineListener = null;
  });

  it('should initialize with navigator.onLine status', () => {
    Object.defineProperty(navigator, 'onLine', {
      writable: true,
      value: true,
    });

    const { result } = renderHook(() => useNetworkStatus());
    expect(result.current.isOnline).toBe(true);
  });

  it('should initialize with offline status when navigator is offline', () => {
    Object.defineProperty(navigator, 'onLine', {
      writable: true,
      value: false,
    });

    const { result } = renderHook(() => useNetworkStatus());
    expect(result.current.isOnline).toBe(false);
  });

  it('should update status when going online', () => {
    Object.defineProperty(navigator, 'onLine', {
      writable: true,
      value: false,
    });

    const { result } = renderHook(() => useNetworkStatus());
    expect(result.current.isOnline).toBe(false);

    // Simulate online event
    act(() => {
      Object.defineProperty(navigator, 'onLine', {
        writable: true,
        value: true,
      });
      if (onlineListener) {
        onlineListener(new Event('online'));
      }
    });

    const state = useOfflineQueueStore.getState();
    expect(state.isOnline).toBe(true);
  });

  it('should update status when going offline', () => {
    Object.defineProperty(navigator, 'onLine', {
      writable: true,
      value: true,
    });

    const { result } = renderHook(() => useNetworkStatus());
    expect(result.current.isOnline).toBe(true);

    // Simulate offline event
    act(() => {
      Object.defineProperty(navigator, 'onLine', {
        writable: true,
        value: false,
      });
      if (offlineListener) {
        offlineListener(new Event('offline'));
      }
    });

    const state = useOfflineQueueStore.getState();
    expect(state.isOnline).toBe(false);
  });

  it('should clean up event listeners on unmount', () => {
    const removeEventListenerSpy = vi.spyOn(window, 'removeEventListener');

    const { unmount } = renderHook(() => useNetworkStatus());
    unmount();

    expect(removeEventListenerSpy).toHaveBeenCalledWith('online', expect.any(Function));
    expect(removeEventListenerSpy).toHaveBeenCalledWith('offline', expect.any(Function));
  });
});
