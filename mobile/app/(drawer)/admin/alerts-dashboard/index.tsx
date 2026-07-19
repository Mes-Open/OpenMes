import { AlertsScreen } from '@/screens/(drawer)/admin/AlertsScreen';

/**
 * Admin Alerts — mirrors the web alerts page (Blocking Issues · Overdue Work
 * Orders · Blocked Work Orders · Open Issues). Data via GET /api/v1/alerts.
 */
export default function AlertsDashboardScreenPage() {
  return <AlertsScreen />;
}
