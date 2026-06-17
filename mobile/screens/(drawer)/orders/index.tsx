import { HubScreen, type HubItem } from '@/components/ui/Hub';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { TabletWorkOrdersDispatch } from '@/screens/tablet/WorkOrders';
import { getRole, useAuthStore } from '@/stores/authStore';

const ITEMS: HubItem[] = [
  {
    key: 'work-orders',
    label: 'Work orders',
    description: 'Browse, filter, and open production orders.',
    icon: 'list-alt',
    route: '/(drawer)/orders/work-orders',
    available: true,
  },
  {
    key: 'imports',
    label: 'CSV imports',
    description: 'History of bulk work-order imports.',
    icon: 'cloud-upload',
    route: '/(drawer)/orders/imports',
    available: true,
  },
];

export function OrdersHub() {
  const role = getRole(useAuthStore((s) => s.user));
  const isAdmin = role === 'Admin';
  const items = isAdmin ? ITEMS : ITEMS.filter((i) => i.key !== 'imports');
  const { useTabletLayout } = useDeviceClass();

  // Tablet (landscape) jumps straight to the Schedule & dispatch screen — the
  // hub's only two children fit better as a single drilldown view. Phone
  // (and tablet in portrait) keeps the hub.
  if (useTabletLayout) return <TabletWorkOrdersDispatch />;

  return (
    <HubScreen
      title="Orders"
      groupLabel="Operations"
      subtitle="Work orders and bulk imports."
      items={items}
    />
  );
}
