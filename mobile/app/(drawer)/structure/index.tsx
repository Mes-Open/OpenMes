/**
 * Section root — no hub screen here: navigation lives in the sidebar / bottom
 * nav + Menu drawer, exactly like the web (web Structure group's first destination is Sites).
 * Redirecting keeps URL-parent back navigation predictable.
 */
import { Redirect } from 'expo-router';

export default function StructureRootRedirect() {
  return <Redirect href="/structure/sites" />;
}
