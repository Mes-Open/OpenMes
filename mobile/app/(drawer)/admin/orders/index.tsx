/**
 * /admin/orders alias — the OrdersHub screen is gone (no web counterpart;
 * navigation lives in the sidebar / bottom nav). Land on All Orders like the
 * web's Orders group does.
 */
import { Redirect } from 'expo-router';

export default function AdminOrdersRedirect() {
  return <Redirect href="/admin/work-orders" />;
}
