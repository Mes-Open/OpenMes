import { Stack } from 'expo-router';

import { DrawerToggleButton } from '@/components/drawer/DrawerToggleButton';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

export default function HrLayout() {
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
      <Stack.Screen name="index" options={{ headerShown: false, title: 'HR' }} />
      <Stack.Screen name="skills/index" options={{ title: 'Skills' }} />
      <Stack.Screen name="wage-groups/index" options={{ title: 'Wage groups' }} />
      <Stack.Screen name="crews/index" options={{ title: 'Crews' }} />
      <Stack.Screen name="workers/index" options={{ title: 'Workers' }} />
    </Stack>
  );
}
