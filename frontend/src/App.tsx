import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MantineProvider } from '@mantine/core';
import { Notifications } from '@mantine/notifications';
import { useEffect } from 'react';

import { LoginPage } from './pages/auth/LoginPage';
import { SelectLinePage } from './pages/operator/SelectLinePage';
import { QueuePage } from './pages/operator/QueuePage';
import { WorkOrderDetailPage } from './pages/operator/WorkOrderDetailPage';
import { SupervisorDashboard } from './pages/supervisor/Dashboard';
import { CsvImportPage } from './pages/admin/CsvImport';
import { AuditLogsPage } from './pages/admin/AuditLogs';
import { ProtectedRoute } from './components/auth/ProtectedRoute';
import { OfflineIndicator } from './components/common/OfflineIndicator';
import { useAuthStore } from './stores/authStore';
import { useNetworkStatus } from './hooks/useNetworkStatus';
import { useOfflineSync } from './hooks/useOfflineSync';

import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

function App() {
  const initializeAuth = useAuthStore((state) => state.initializeAuth);

  // Initialize network status monitoring and offline sync
  useNetworkStatus();
  useOfflineSync();

  useEffect(() => {
    initializeAuth();
  }, [initializeAuth]);

  return (
    <QueryClientProvider client={queryClient}>
      <MantineProvider>
        <Notifications position="top-right" />
        <OfflineIndicator />
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route
              path="/operator/select-line"
              element={
                <ProtectedRoute requireRole="Operator">
                  <SelectLinePage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/operator/queue"
              element={
                <ProtectedRoute requireRole="Operator">
                  <QueuePage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/operator/work-order/:id"
              element={
                <ProtectedRoute requireRole="Operator">
                  <WorkOrderDetailPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/supervisor/dashboard"
              element={
                <ProtectedRoute requireRole="Supervisor">
                  <SupervisorDashboard />
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/csv-import"
              element={
                <ProtectedRoute requireRole="Admin">
                  <CsvImportPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/audit-logs"
              element={
                <ProtectedRoute requireRole="Admin">
                  <AuditLogsPage />
                </ProtectedRoute>
              }
            />
            <Route path="/" element={<Navigate to="/login" replace />} />
            <Route path="*" element={<Navigate to="/login" replace />} />
          </Routes>
        </BrowserRouter>
      </MantineProvider>
    </QueryClientProvider>
  );
}

export default App;
