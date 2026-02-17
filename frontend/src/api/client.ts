import axios, { AxiosError } from 'axios';
import type { InternalAxiosRequestConfig } from 'axios';
import { useOfflineQueueStore } from '../stores/offlineQueueStore';
import { notifications } from '@mantine/notifications';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

// Create axios instance
export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Determine action type from URL for offline queue
function getActionType(url: string): string {
  if (url.includes('/start')) return 'START_STEP';
  if (url.includes('/complete')) return 'COMPLETE_STEP';
  if (url.includes('/issues')) return 'CREATE_ISSUE';
  if (url.includes('/batches')) return 'UPDATE_BATCH';
  return 'GENERIC_ACTION';
}

// Check if request should be queued when offline
function isQueueableRequest(method: string): boolean {
  return ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase());
}

// Request interceptor - add auth token
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem('auth_token');
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor - handle errors and offline queuing
apiClient.interceptors.response.use(
  (response) => {
    return response;
  },
  (error: AxiosError) => {
    const config = error.config as InternalAxiosRequestConfig;

    // Handle network errors (offline)
    if (!error.response && error.message === 'Network Error') {
      const { isOnline, addToQueue } = useOfflineQueueStore.getState();

      // Only queue write operations when offline
      if (!isOnline && config && isQueueableRequest(config.method || '')) {
        const actionType = getActionType(config.url || '');

        addToQueue({
          type: actionType as any,
          url: config.url || '',
          method: (config.method?.toUpperCase() as any) || 'POST',
          data: config.data,
          headers: config.headers as Record<string, string>,
          maxRetries: 3,
        });

        notifications.show({
          title: 'Offline Mode',
          message: 'Action queued. Will sync when connection restored.',
          color: 'yellow',
        });

        // Return a rejected promise with offline indicator
        return Promise.reject({
          ...error,
          isOfflineQueued: true,
        });
      }
    }

    // Handle authorization errors
    if (error.response?.status === 401) {
      // Unauthorized - clear token and redirect to login
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }

    return Promise.reject(error);
  }
);

export default apiClient;
