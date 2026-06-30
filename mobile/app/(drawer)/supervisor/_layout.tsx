import { Redirect, Stack } from 'expo-router';

import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';

/**
 * Supervisor route group — hub + analytics + reports + supervisor
 * utilities. Hard gate: anyone whose role is neither Supervisor nor Admin
 * is redirected to the Operator hub. Backend remains the source of truth
 * for authorization on the underlying API calls.
 */
export default function SupervisorLayout() {
  const user = useAuthStore((s) => s.user);
  // Wait for auth — don't fall through to /operator while the store is
  // still hydrating or just after a 401 cleared the session.
  if (!user) return null;
  if (!isSupervisorOrAdmin(user)) {
    return <Redirect href="/operator" />;
  }
  return <Stack screenOptions={{ headerShown: false }} />;
}
