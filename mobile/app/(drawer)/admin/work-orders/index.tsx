import { AdminWorkOrdersScreen } from '@/screens/(drawer)/admin/AdminWorkOrders';

/**
 * Admin Work Orders — mirrors the web admin/work-orders table. Data via
 * /api/v1/work-orders (useWorkOrders); rows open the WO detail.
 */
export default function AdminWorkOrdersPage() {
  return <AdminWorkOrdersScreen />;
}
