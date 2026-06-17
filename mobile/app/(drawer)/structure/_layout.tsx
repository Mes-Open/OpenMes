import { Stack } from 'expo-router';

import { DrawerToggleButton } from '@/components/drawer/DrawerToggleButton';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

export default function StructureLayout() {
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
      <Stack.Screen
        name="index"
        // Custom in-screen header (logo, tabs, breadcrumb) provides everything,
        // so the Stack header is hidden here to avoid the double bar.
        options={{ headerShown: false, title: 'Structure' }}
      />
      <Stack.Screen name="lines/index" options={{ title: 'Lines' }} />
      <Stack.Screen
        name="lines/[id]/workstations/index"
        options={{ title: 'Workstations' }}
      />
      <Stack.Screen name="workstation-types/index" options={{ title: 'Workstation types' }} />
      <Stack.Screen name="factories/index" options={{ title: 'Factories' }} />
      <Stack.Screen name="factories/[id]/divisions/index" options={{ title: 'Divisions' }} />
      <Stack.Screen name="lines/[id]/statuses/index" options={{ title: 'Line statuses' }} />
    </Stack>
  );
}
