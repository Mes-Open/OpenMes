import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MantineProvider } from '@mantine/core';
import { OfflineIndicator } from './OfflineIndicator';
import { useOfflineQueueStore } from '../../stores/offlineQueueStore';

// Wrapper component with MantineProvider
const renderWithMantine = (component: React.ReactElement) => {
  return render(<MantineProvider>{component}</MantineProvider>);
};

// Mock the hooks
vi.mock('../../hooks/useNetworkStatus', () => ({
  useNetworkStatus: vi.fn(() => ({ isOnline: true })),
}));

vi.mock('../../hooks/useOfflineSync', () => ({
  useOfflineSync: vi.fn(() => ({
    processQueue: vi.fn(),
    isSyncing: false,
    queuedCount: 0,
  })),
}));

import { useNetworkStatus } from '../../hooks/useNetworkStatus';
import { useOfflineSync } from '../../hooks/useOfflineSync';

describe('OfflineIndicator', () => {
  beforeEach(() => {
    // Reset store
    const store = useOfflineQueueStore.getState();
    store.clearQueue();
    store.clearFailedActions();
    vi.clearAllMocks();
  });

  it('should not render when online with no pending actions', () => {
    vi.mocked(useNetworkStatus).mockReturnValue({ isOnline: true });
    vi.mocked(useOfflineSync).mockReturnValue({
      processQueue: vi.fn(),
      isSyncing: false,
      queuedCount: 0,
    });

    renderWithMantine(<OfflineIndicator />);
    // Should not show any offline/syncing indicators
    expect(screen.queryByText('Offline Mode')).not.toBeInTheDocument();
    expect(screen.queryByText('Syncing...')).not.toBeInTheDocument();
    expect(screen.queryByText('Sync Failed')).not.toBeInTheDocument();
  });

  it('should show offline mode when offline', () => {
    vi.mocked(useNetworkStatus).mockReturnValue({ isOnline: false });
    vi.mocked(useOfflineSync).mockReturnValue({
      processQueue: vi.fn(),
      isSyncing: false,
      queuedCount: 0,
    });

    renderWithMantine(<OfflineIndicator />);
    expect(screen.getByText('Offline Mode')).toBeInTheDocument();
    expect(
      screen.getByText(/You are currently offline/i)
    ).toBeInTheDocument();
  });

  it('should show queued action count when offline', () => {
    vi.mocked(useNetworkStatus).mockReturnValue({ isOnline: false });
    vi.mocked(useOfflineSync).mockReturnValue({
      processQueue: vi.fn(),
      isSyncing: false,
      queuedCount: 3,
    });

    renderWithMantine(<OfflineIndicator />);
    expect(screen.getByText('3 queued actions')).toBeInTheDocument();
  });

  it('should show syncing status when syncing', () => {
    vi.mocked(useNetworkStatus).mockReturnValue({ isOnline: true });
    vi.mocked(useOfflineSync).mockReturnValue({
      processQueue: vi.fn(),
      isSyncing: true,
      queuedCount: 2,
    });

    renderWithMantine(<OfflineIndicator />);
    expect(screen.getByText('Syncing...')).toBeInTheDocument();
    expect(screen.getByText('Syncing 2 queued action(s)...')).toBeInTheDocument();
  });

  it('should show failed actions when present', () => {
    const { addToQueue, markActionAsFailed } = useOfflineQueueStore.getState();

    // Add and fail an action
    addToQueue({
      type: 'START_STEP',
      url: '/test',
      method: 'POST',
      maxRetries: 3,
    });
    const actionId = useOfflineQueueStore.getState().queue[0].id;
    markActionAsFailed(actionId, 'Test error');

    vi.mocked(useNetworkStatus).mockReturnValue({ isOnline: true });
    vi.mocked(useOfflineSync).mockReturnValue({
      processQueue: vi.fn(),
      isSyncing: false,
      queuedCount: 0,
    });

    renderWithMantine(<OfflineIndicator />);
    expect(screen.getByText('Sync Failed')).toBeInTheDocument();
    expect(screen.getByText(/1 action\(s\) failed to sync/i)).toBeInTheDocument();
  });

  it('should show pending sync when online with queued actions', () => {
    vi.mocked(useNetworkStatus).mockReturnValue({ isOnline: true });
    vi.mocked(useOfflineSync).mockReturnValue({
      processQueue: vi.fn(),
      isSyncing: false,
      queuedCount: 5,
    });

    renderWithMantine(<OfflineIndicator />);
    expect(screen.getByText('Pending Sync')).toBeInTheDocument();
    expect(screen.getByText('5 action(s) waiting to sync.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /sync now/i })).toBeInTheDocument();
  });
});
