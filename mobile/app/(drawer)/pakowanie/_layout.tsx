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
      <Stack.Screen name="label-templates/index" options={{ headerShown: false, title: 'Label templates' }} />
      <Stack.Screen name="label-templates/new" options={{ headerShown: false, title: 'New label template' }} />
      <Stack.Screen name="label-templates/[id]/edit" options={{ headerShown: false, title: 'Edit label template' }} />
    </Stack>
  );
}
