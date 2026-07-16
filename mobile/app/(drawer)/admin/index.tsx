/**
 * Section root — no hub screen here: navigation lives in the sidebar / bottom
 * nav + Menu drawer, exactly like the web (the web has no /admin hub — the sidebar is the hub; admins land on the dashboard).
 * Redirecting keeps URL-parent back navigation predictable.
 */
import { Redirect } from 'expo-router';

export default function AdminRootRedirect() {
  return <Redirect href="/admin/dashboard" />;
}
