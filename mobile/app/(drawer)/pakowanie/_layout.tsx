import { Stack } from 'expo-router';

import { DrawerToggleButton } from '@/components/drawer/DrawerToggleButton';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

export default function PakowanieLayout() {
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
      <Stack.Screen name="index" options={{ headerShown: false, title: 'Packaging' }} />
      <Stack.Screen name="eans/index" options={{ title: 'EANs' }} />
    </Stack>
  );
}
