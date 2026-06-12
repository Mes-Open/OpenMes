import { WorkOrderDetailScreen } from '@/screens/work-orders/[id]';

/**
 * Admin Work Order detail — same screen file as /work-orders/[id], mounted
 * under /admin/work-orders/[id] so the URL stays in admin context when an
 * admin opens a WO from /admin/orders. Back navigation pops to the admin
 * Stack (returning to /admin/orders) instead of leaving the admin URL space.
 */
export default function AdminWorkOrderDetailPage() {
  return <WorkOrderDetailScreen />;
}
