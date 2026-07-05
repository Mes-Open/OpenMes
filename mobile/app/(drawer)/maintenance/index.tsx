/**
 * Section root — no hub screen here: navigation lives in the sidebar / bottom
 * nav + Menu drawer, exactly like the web (web Maintenance group's first destination is Maintenance Events).
 * Redirecting keeps URL-parent back navigation predictable.
 */
import { Redirect } from 'expo-router';

export default function MaintenanceRootRedirect() {
  return <Redirect href="/maintenance/events" />;
}
