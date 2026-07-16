/**
 * Section root — no hub screen here: navigation lives in the sidebar / bottom
 * nav + Menu drawer, exactly like the web (web HR group's first destination is Workers).
 * Redirecting keeps URL-parent back navigation predictable.
 */
import { Redirect } from 'expo-router';

export default function HrRootRedirect() {
  return <Redirect href="/hr/workers" />;
}
