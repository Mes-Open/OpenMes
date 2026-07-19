/**
 * Section root — no hub screen here: navigation lives in the sidebar / bottom
 * nav + Menu drawer, exactly like the web (web Production group's first destination is Product Types).
 * Redirecting keeps URL-parent back navigation predictable.
 */
import { Redirect } from 'expo-router';

export default function ProductionRootRedirect() {
  return <Redirect href="/production/product-types" />;
}
