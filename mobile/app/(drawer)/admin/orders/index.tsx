import { OrdersHub } from '@/screens/(drawer)/orders/index';

/**
 * Admin Orders — same screen as /orders, mounted under /admin/orders so the
 * URL stays in admin context when the admin navigates from their sidebar.
 * The screen file is shared so any change to OrdersHub shows up in both
 * routes; only the URL differs.
 */
export default function AdminOrdersPage() {
  return <OrdersHub />;
}
