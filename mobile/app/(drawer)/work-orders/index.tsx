/**
 * /work-orders has detail routes but no list of its own — the breadcrumb (and
 * any deep link) lands on the admin Work Orders table, like the web.
 */
import { Redirect } from 'expo-router';

export default function WorkOrdersIndexRedirect() {
  return <Redirect href="/admin/work-orders" />;
}
