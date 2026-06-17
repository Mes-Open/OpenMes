import { Redirect, Stack } from 'expo-router';

import { getRole, useAuthStore } from '@/stores/authStore';

/**
 * Operator route group — landing hub for the Operator role. Hard gate:
 * supervisors and admins are bounced to their own hub when they land here
 * (otherwise pressing back from a deep route could accidentally drop an
 * admin into the operator hub and make it look like their role changed).
 */
export default function OperatorLayout() {
  const user = useAuthStore((s) => s.user);
  // Auth still rehydrating or 401-cleared — defer to AuthGate (which will
  // bounce to /login). Returning null avoids showing operator chrome to a
  // user who isn't actually an operator.
  if (!user) return null;
  const role = getRole(user);
  if (role === 'Admin') return <Redirect href="/admin" />;
  if (role === 'Supervisor') return <Redirect href="/supervisor" />;
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" options={{ title: 'Operator' }} />
      <Stack.Screen name="today/index" options={{ title: 'Today' }} />
      <Stack.Screen name="orders/index" options={{ title: 'Orders' }} />
      <Stack.Screen name="scan/index" options={{ title: 'Scan' }} />
      <Stack.Screen name="alerts/index" options={{ title: 'Alerts' }} />
      <Stack.Screen name="shift/index" options={{ title: 'Shift' }} />
    </Stack>
  );
}
