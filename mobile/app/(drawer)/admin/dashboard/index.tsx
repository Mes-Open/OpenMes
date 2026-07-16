import { AdminDashboardScreen } from '@/screens/(drawer)/admin/AdminDashboard';

/**
 * Admin Dashboard — mirrors the web Inertia admin dashboard (KPIs, OEE Overview,
 * Inbound QC, Materials, Scrap, Non-conformances, Recent work orders, Open
 * issues). Data via GET /api/v1/admin/dashboard.
 */
export default function AdminDashboardPage() {
  return <AdminDashboardScreen />;
}
