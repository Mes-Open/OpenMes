import { Stack } from 'expo-router';

import { DrawerToggleButton } from '@/components/drawer/DrawerToggleButton';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

export default function MaintenanceLayout() {
  const scheme = useColorScheme();
  const palette = Colors[scheme];

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        headerStyle: { backgroundColor: palette.surface },
        headerTintColor: palette.text,
        headerTitleStyle: { fontWeight: '700' },
      }}>
      <Stack.Screen name="index" options={{ headerShown: false, title: 'Maintenance' }} />
      <Stack.Screen name="tools/index" options={{ title: 'Tools' }} />
      <Stack.Screen name="events/index" options={{ title: 'Maintenance events' }} />
      <Stack.Screen name="schedules/index" options={{ headerShown: false, title: 'Maintenance schedules' }} />
      <Stack.Screen name="schedules/new" options={{ headerShown: false, title: 'New schedule' }} />
      <Stack.Screen name="schedules/[id]/edit" options={{ headerShown: false, title: 'Edit schedule' }} />
    </Stack>
  );
}
